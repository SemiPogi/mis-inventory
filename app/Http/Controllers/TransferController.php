<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\DepartmentTransfer;
use App\Models\DepartmentTransferItem;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Department-to-Department Transfers.
 *
 * Workflow: from_dept creates transfer → from_dept Head approves
 *  → items deducted from source, to_dept user acknowledges
 *  → items added to destination → status = completed.
 */
class TransferController extends Controller
{
    public function index(): View
    {
        $scope = $this->deptScope();
        $transfers = DepartmentTransfer::with(['fromDept', 'toDept', 'requestedBy', 'items'])
            ->when($scope, fn($q) => $q->where(function ($q) use ($scope) {
                $q->where('from_dept_id', $scope)->orWhere('to_dept_id', $scope);
            }))
            ->latest()
            ->paginate(20);

        return view('transfers.index', compact('transfers'));
    }

    public function create(): View
    {
        $scope = $this->deptScope();
        $myDept = $scope ? Department::find($scope) : null;
        $departments = Department::where('is_active', true)->orderBy('name')->get();
        $items = Item::when($scope, fn($q) => $q->where('department_id', $scope))
            ->where('current_qty', '>', 0)
            ->orderBy('name')
            ->get();

        return view('transfers.create', compact('myDept', 'departments', 'items'));
    }

    public function store(Request $request): RedirectResponse
    {
        $scope = $this->deptScope();

        $data = $request->validate([
            'to_dept_id'         => ['required', 'exists:departments,id'],
            'purpose'            => ['required', 'string', 'max:500'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.item_id'    => ['required', 'exists:items,id'],
            'items.*.qty'        => ['required', 'integer', 'min:1'],
        ]);

        // Determine source dept
        $fromDeptId = $scope ?? auth()->user()->department_id;

        // Cannot transfer to self
        if ((int) $data['to_dept_id'] === $fromDeptId) {
            return back()->withErrors(['to_dept_id' => 'Cannot transfer to your own department.'])->withInput();
        }

        // Validate each item belongs to source dept and has enough qty
        foreach ($data['items'] as $line) {
            $item = Item::find($line['item_id']);
            if (! $item || $item->department_id !== $fromDeptId) {
                return back()->withErrors(['items' => "Item #{$line['item_id']} does not belong to your department."])->withInput();
            }
            if ($item->current_qty < $line['qty']) {
                return back()->withErrors(['items' => "Insufficient stock for '{$item->name}' (have {$item->current_qty}, requested {$line['qty']})."])->withInput();
            }
        }

        $transfer = DepartmentTransfer::create([
            'transfer_number' => DepartmentTransfer::generateTransferNumber(),
            'from_dept_id'    => $fromDeptId,
            'to_dept_id'      => $data['to_dept_id'],
            'status'          => 'pending_head',
            'purpose'         => $data['purpose'],
            'notes'           => $data['notes'] ?? null,
            'requested_by_id' => auth()->id(),
        ]);

        foreach ($data['items'] as $line) {
            $item = Item::find($line['item_id']);
            DepartmentTransferItem::create([
                'department_transfer_id' => $transfer->id,
                'item_id'                => $item->id,
                'item_name_snapshot'     => $item->name,
                'unit'                   => $item->unit,
                'qty'                    => $line['qty'],
            ]);
        }

        // Notify dept heads of the outgoing transfer
        $fromDept = Department::find($fromDeptId);
        $heads = \App\Models\User::where('department_id', $fromDeptId)->where('is_head', true)->get();
        foreach ($heads as $head) {
            Notification::notify($head, 'transfer_pending_head', 'Transfer Needs Approval',
                "{$transfer->transfer_number} from {$fromDept->name} is pending your approval.",
                ['url' => route('transfers.show', $transfer)]
            );
        }

        return redirect()->route('transfers.show', $transfer)
            ->with('success', "{$transfer->transfer_number} submitted for head approval.");
    }

    public function show(DepartmentTransfer $transfer): View
    {
        $scope = $this->deptScope();
        if ($scope && $transfer->from_dept_id !== $scope && $transfer->to_dept_id !== $scope) {
            abort(403);
        }

        $transfer->load(['fromDept', 'toDept', 'requestedBy', 'headApprovedBy', 'acknowledgedBy', 'items.item', 'attachments.uploadedBy']);
        return view('transfers.show', compact('transfer'));
    }

    /**
     * Head approves the transfer — deducts stock from source dept immediately.
     */
    public function approve(DepartmentTransfer $transfer): RedirectResponse
    {
        $this->authorizeHead($transfer);

        if (! $transfer->isPendingHead()) {
            return back()->with('error', 'Transfer is not pending approval.');
        }

        $transfer->load('items.item');

        // Deduct from source inventory
        foreach ($transfer->items as $tItem) {
            $item = $tItem->item;
            if ($item && $item->current_qty >= $tItem->qty) {
                $item->current_qty -= $tItem->qty;
                $item->save();
            }
        }

        $transfer->update([
            'status'               => 'approved',
            'head_approved_by_id'  => auth()->id(),
            'head_approved_at'     => now(),
        ]);

        // Notify the requesting staff and the destination dept
        Notification::notify($transfer->requested_by_id, 'transfer_approved',
            'Transfer Approved',
            "{$transfer->transfer_number} was approved. Items will be sent to {$transfer->toDept->name}.",
            ['url' => route('transfers.show', $transfer)]
        );

        // Notify to_dept staff to expect incoming items
        $toStaff = \App\Models\User::where('department_id', $transfer->to_dept_id)->get();
        foreach ($toStaff as $user) {
            Notification::notify($user, 'transfer_incoming',
                'Incoming Transfer',
                "{$transfer->transfer_number} from {$transfer->fromDept->name} is on its way.",
                ['url' => route('transfers.show', $transfer)]
            );
        }

        return redirect()->route('transfers.head.index')
            ->with('success', "{$transfer->transfer_number} approved. Stock deducted from {$transfer->fromDept->name}.");
    }

    /**
     * Head rejects the transfer.
     */
    public function reject(Request $request, DepartmentTransfer $transfer): RedirectResponse
    {
        $this->authorizeHead($transfer);

        $request->validate(['notes' => ['required', 'string', 'max:500']]);

        if (! $transfer->isPendingHead()) {
            return back()->with('error', 'Transfer is not pending approval.');
        }

        $transfer->update([
            'status' => 'rejected',
            'notes'  => $request->notes,
        ]);

        Notification::notify($transfer->requested_by_id, 'transfer_rejected',
            'Transfer Rejected',
            "{$transfer->transfer_number} was rejected: {$request->notes}",
            ['url' => route('transfers.show', $transfer)]
        );

        return redirect()->route('transfers.head.index')
            ->with('success', "{$transfer->transfer_number} rejected.");
    }

    /**
     * Destination dept acknowledges receipt — adds items to their inventory.
     */
    public function acknowledge(DepartmentTransfer $transfer): RedirectResponse
    {
        $scope = $this->deptScope();
        if ($scope && $transfer->to_dept_id !== $scope) {
            abort(403);
        }

        if (! $transfer->isApproved()) {
            return back()->with('error', 'Transfer must be approved before acknowledging.');
        }

        $transfer->load('items.item');

        foreach ($transfer->items as $tItem) {
            $existing = Item::where('name', $tItem->item_name_snapshot)
                ->where('department_id', $transfer->to_dept_id)
                ->first();

            if ($existing) {
                $existing->total_qty_received += $tItem->qty;
                $existing->current_qty        += $tItem->qty;
                $existing->save();
            } else {
                $existing = Item::create([
                    'name'               => $tItem->item_name_snapshot,
                    'unit'               => $tItem->unit,
                    'total_qty_received' => $tItem->qty,
                    'current_qty'        => $tItem->qty,
                    'department_id'      => $transfer->to_dept_id,
                ]);
            }

            // Audit trail
            Transaction::create([
                'type'                  => 'received',
                'item_id'               => $existing->id,
                'item_name_snapshot'    => $tItem->item_name_snapshot,
                'qty'                   => $tItem->qty,
                'unit'                  => $tItem->unit,
                'received_from'         => "Transfer from {$transfer->fromDept->name}",
                'ris_iar_number'        => $transfer->transfer_number,
                'date_received'         => now()->toDateString(),
                'received_by_user_id'   => auth()->id(),
                'acknowledgment_status' => 'acknowledged',
                'department_id'         => $transfer->to_dept_id,
            ]);
        }

        $transfer->update([
            'status'             => 'completed',
            'acknowledged_by_id' => auth()->id(),
            'acknowledged_at'    => now(),
        ]);

        Notification::notify($transfer->requested_by_id, 'transfer_completed',
            'Transfer Completed',
            "{$transfer->transfer_number} has been acknowledged by {$transfer->toDept->name}.",
            ['url' => route('transfers.show', $transfer)]
        );

        return redirect()->route('transfers.show', $transfer)
            ->with('success', 'Transfer acknowledged. Items added to your inventory.');
    }

    /**
     * Pending transfers waiting for this head's approval.
     */
    public function headQueue(): View
    {
        $user = auth()->user();
        if (! $user->is_head && ! $user->isAdmin()) {
            abort(403);
        }

        $deptId = $user->department_id;
        $pending = DepartmentTransfer::with(['fromDept', 'toDept', 'requestedBy', 'items'])
            ->where('status', 'pending_head')
            ->when(! $user->isAdmin(), fn($q) => $q->where('from_dept_id', $deptId))
            ->latest()
            ->get();

        return view('transfers.head-queue', compact('pending'));
    }

    // ── Authorization ──────────────────────────────────────────────────────

    private function authorizeHead(DepartmentTransfer $transfer): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) return;
        if (! $user->is_head || $user->department_id !== $transfer->from_dept_id) {
            abort(403, 'Only the source department head can approve this transfer.');
        }
    }
}

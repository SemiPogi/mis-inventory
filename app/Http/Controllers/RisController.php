<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\RisRequest;
use App\Models\RisItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RisController extends Controller
{
    /**
     * List RIS requests for the current department.
     * Supply staff see all incoming RIS (their own + all depts requesting from supply).
     * Regular dept staff see only their own dept's requests.
     */
    public function index(): View
    {
        $scope = $this->deptScope();
        $ris = RisRequest::with(['requestingDept', 'requestedBy', 'items'])
            ->when($scope, fn($q) => $q->where('requesting_dept_id', $scope))
            ->latest()
            ->paginate(20);

        return view('ris.index', compact('ris'));
    }

    public function create(): View
    {
        $departments = auth()->user()->isAdmin()
            ? Department::where('is_active', true)->orderBy('name')->get()
            : collect();

        return view('ris.create', compact('departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $isAdmin = auth()->user()->isAdmin();

        $data = $request->validate([
            'requesting_dept_id' => [$isAdmin ? 'required' : 'nullable', 'exists:departments,id'],
            'purpose'            => ['required', 'string', 'max:500'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.item_name'  => ['required', 'string', 'max:255'],
            'items.*.unit'       => ['required', 'string', 'max:50'],
            'items.*.requested_qty' => ['required', 'integer', 'min:1'],
            'items.*.stock_no'   => ['nullable', 'string', 'max:100'],
            'items.*.remarks'    => ['nullable', 'string', 'max:255'],
        ]);

        $deptId = $isAdmin
            ? $data['requesting_dept_id']
            : auth()->user()->department_id;

        $ris = RisRequest::create([
            'ris_number'         => RisRequest::generateRisNumber(),
            'requesting_dept_id' => $deptId,
            'status'             => 'pending_head',
            'purpose'            => $data['purpose'],
            'notes'              => $data['notes'] ?? null,
            'requested_by_id'    => auth()->id(),
        ]);

        foreach ($data['items'] as $line) {
            RisItem::create([
                'ris_request_id' => $ris->id,
                'stock_no'       => $line['stock_no'] ?? null,
                'item_name'      => $line['item_name'],
                'unit'           => $line['unit'],
                'requested_qty'  => $line['requested_qty'],
                'remarks'        => $line['remarks'] ?? null,
            ]);
        }

        return redirect()->route('ris.show', $ris)
            ->with('success', "RIS {$ris->ris_number} submitted for head approval.");
    }

    public function show(RisRequest $ris): View
    {
        $scope = $this->deptScope();
        // Staff can only view their dept's RIS; supply/admin/accounting can view all
        if ($scope && $ris->requesting_dept_id !== $scope) {
            abort(403);
        }
        $ris->load(['requestingDept', 'requestedBy', 'headApprovedBy', 'issuedBy', 'acknowledgedBy', 'items']);
        return view('ris.show', compact('ris'));
    }

    /**
     * Dept acknowledges receipt of issued items — moves items into dept inventory.
     */
    public function acknowledge(Request $request, RisRequest $ris): RedirectResponse
    {
        $user = auth()->user();
        if (! $user->isAdmin() && $user->department_id !== $ris->requesting_dept_id) {
            abort(403);
        }

        if (! $ris->isIssued()) {
            return back()->with('error', 'RIS must be in Issued status to acknowledge.');
        }

        $ris->load('items');

        // Add issued items to department's inventory
        foreach ($ris->items as $item) {
            if (($item->issued_qty ?? 0) <= 0) continue;

            $existing = \App\Models\Item::where('name', $item->item_name)
                ->where('department_id', $ris->requesting_dept_id)
                ->first();

            if ($existing) {
                $existing->total_qty_received += $item->issued_qty;
                $existing->current_qty        += $item->issued_qty;
                $existing->save();
            } else {
                \App\Models\Item::create([
                    'name'               => $item->item_name,
                    'unit'               => $item->unit,
                    'total_qty_received' => $item->issued_qty,
                    'current_qty'        => $item->issued_qty,
                    'department_id'      => $ris->requesting_dept_id,
                    'created_by'         => auth()->id(),
                ]);
            }

            // Log as received transaction
            \App\Models\Transaction::create([
                'type'                  => 'received',
                'item_id'               => $existing?->id ?? \App\Models\Item::where('name', $item->item_name)->where('department_id', $ris->requesting_dept_id)->value('id'),
                'item_name_snapshot'    => $item->item_name,
                'qty'                   => $item->issued_qty,
                'unit'                  => $item->unit,
                'received_from'         => 'Supply (RIS)',
                'ris_iar_number'        => $ris->ris_number,
                'date_received'         => now()->toDateString(),
                'received_by_user_id'   => auth()->id(),
                'acknowledgment_status' => 'acknowledged',
                'department_id'         => $ris->requesting_dept_id,
            ]);
        }

        $ris->update([
            'status'             => 'completed',
            'acknowledged_by_id' => auth()->id(),
            'acknowledged_at'    => now(),
        ]);

        return redirect()->route('ris.show', $ris)
            ->with('success', 'RIS acknowledged. Items added to your inventory.');
    }

    public function print(RisRequest $ris): View
    {
        if (! $ris->isCompleted()) {
            abort(403, 'RIS must be completed before printing.');
        }
        $ris->load(['requestingDept', 'requestedBy', 'headApprovedBy', 'issuedBy', 'acknowledgedBy', 'items']);
        return view('ris.print', compact('ris'));
    }
}

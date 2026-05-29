<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\IarItem;
use App\Models\IarRecord;
use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Inspection and Acceptance Report — Supply department only.
 * Records supplier deliveries; on acceptance, stock is added to supply inventory.
 */
class IarController extends Controller
{
    public function index(): View
    {
        $this->authorizeSupply();

        $records = IarRecord::with(['createdBy', 'items'])
            ->latest()
            ->paginate(20);

        return view('iar.index', compact('records'));
    }

    public function create(): View
    {
        $this->authorizeSupply();
        return view('iar.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSupply();

        $data = $request->validate([
            'supplier'            => ['required', 'string', 'max:255'],
            'purchase_order_no'   => ['nullable', 'string', 'max:100'],
            'date_of_delivery'    => ['nullable', 'date'],
            'date_of_inspection'  => ['nullable', 'date'],
            'notes'               => ['nullable', 'string', 'max:1000'],
            'items'               => ['required', 'array', 'min:1'],
            'items.*.item_name'   => ['required', 'string', 'max:255'],
            'items.*.unit'        => ['required', 'string', 'max:50'],
            'items.*.qty_delivered'  => ['required', 'integer', 'min:0'],
            'items.*.qty_accepted'   => ['required', 'integer', 'min:0'],
            'items.*.unit_cost'      => ['required', 'numeric', 'min:0'],
            'items.*.description'    => ['nullable', 'string', 'max:255'],
            'items.*.remarks'        => ['nullable', 'string', 'max:255'],
        ]);

        $supplyDept = Department::supplyHub();
        $deptId = $supplyDept?->id ?? auth()->user()->department_id;

        $iar = IarRecord::create([
            'iar_number'          => IarRecord::generateIarNumber(),
            'department_id'       => $deptId,
            'supplier'            => $data['supplier'],
            'purchase_order_no'   => $data['purchase_order_no'] ?? null,
            'date_of_delivery'    => $data['date_of_delivery'] ?? null,
            'date_of_inspection'  => $data['date_of_inspection'] ?? null,
            'status'              => 'draft',
            'notes'               => $data['notes'] ?? null,
            'created_by_id'       => auth()->id(),
        ]);

        foreach ($data['items'] as $line) {
            IarItem::create([
                'iar_record_id' => $iar->id,
                'item_name'     => $line['item_name'],
                'unit'          => $line['unit'],
                'qty_delivered' => $line['qty_delivered'],
                'qty_accepted'  => $line['qty_accepted'],
                'unit_cost'     => $line['unit_cost'],
                'description'   => $line['description'] ?? null,
                'remarks'       => $line['remarks'] ?? null,
            ]);
        }

        return redirect()->route('iar.show', $iar)
            ->with('success', "{$iar->iar_number} created as Draft.");
    }

    public function show(IarRecord $iar): View
    {
        $this->authorizeSupply();
        $iar->load(['createdBy', 'items', 'attachments.uploadedBy']);
        return view('iar.show', compact('iar'));
    }

    /**
     * Accept the IAR — adds accepted items to Supply inventory.
     */
    public function accept(IarRecord $iar): RedirectResponse
    {
        $this->authorizeSupply();

        if (! $iar->isDraft()) {
            return back()->with('error', 'Only Draft IARs can be accepted.');
        }

        $iar->load('items');
        $supplyDept = Department::supplyHub();
        $deptId = $supplyDept?->id ?? auth()->user()->department_id;

        foreach ($iar->items as $iarItem) {
            if ($iarItem->qty_accepted <= 0) continue;

            $existing = Item::where('name', $iarItem->item_name)
                ->where('department_id', $deptId)
                ->first();

            if ($existing) {
                $existing->total_qty_received += $iarItem->qty_accepted;
                $existing->current_qty        += $iarItem->qty_accepted;
                $existing->save();
                $itemId = $existing->id;
            } else {
                $newItem = Item::create([
                    'name'               => $iarItem->item_name,
                    'unit'               => $iarItem->unit,
                    'total_qty_received' => $iarItem->qty_accepted,
                    'current_qty'        => $iarItem->qty_accepted,
                    'department_id'      => $deptId,
                ]);
                $itemId = $newItem->id;
            }

            Transaction::create([
                'type'                  => 'received',
                'item_id'               => $itemId,
                'item_name_snapshot'    => $iarItem->item_name,
                'qty'                   => $iarItem->qty_accepted,
                'unit'                  => $iarItem->unit,
                'received_from'         => $iar->supplier,
                'ris_iar_number'        => $iar->iar_number,
                'date_received'         => now()->toDateString(),
                'received_by_user_id'   => auth()->id(),
                'acknowledgment_status' => 'acknowledged',
                'department_id'         => $deptId,
            ]);
        }

        $iar->update(['status' => 'accepted']);

        return redirect()->route('iar.show', $iar)
            ->with('success', "{$iar->iar_number} accepted. Items added to Supply inventory.");
    }

    /**
     * Reject the IAR.
     */
    public function reject(Request $request, IarRecord $iar): RedirectResponse
    {
        $this->authorizeSupply();

        $request->validate(['notes' => ['required', 'string', 'max:500']]);

        if (! $iar->isDraft()) {
            return back()->with('error', 'Only Draft IARs can be rejected.');
        }

        $iar->update(['status' => 'rejected', 'notes' => $request->notes]);

        return redirect()->route('iar.show', $iar)
            ->with('success', "{$iar->iar_number} rejected.");
    }

    private function authorizeSupply(): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) return;

        $supplyDept = Department::supplyHub();
        if (! $supplyDept || $user->department_id !== $supplyDept->id) {
            abort(403, 'Only Supply department staff can manage IARs.');
        }
    }
}

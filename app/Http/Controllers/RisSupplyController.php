<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Item;
use App\Models\RisRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Supply department processes RIS requests:
 * views the queue, sets issued_qty per line, and marks items as issued.
 */
class RisSupplyController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeSupply();

        $query = RisRequest::with(['requestingDept', 'requestedBy', 'items'])
            ->whereIn('status', ['pending_supply', 'issued']);

        if ($request->filled('dept')) {
            $query->where('requesting_dept_id', $request->dept);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $queue = $query->latest()->paginate(20);
        $departments = Department::where('is_active', true)->orderBy('name')->get();

        return view('ris.supply-queue', compact('queue', 'departments'));
    }

    /**
     * Supply reviews a pending_supply RIS and sets issued_qty per line.
     */
    public function review(RisRequest $ris): View
    {
        $this->authorizeSupply();

        if (! $ris->isPendingSupply()) {
            abort(403, 'This RIS is not pending supply processing.');
        }

        $ris->load(['requestingDept', 'requestedBy', 'items']);

        // Get current supply stock so supply staff can see available qty
        $supplyDept = Department::supplyHub();
        $supplyItems = $supplyDept
            ? Item::where('department_id', $supplyDept->id)->orderBy('name')->get(['id', 'name', 'current_qty', 'unit'])
            : collect();

        return view('ris.supply-review', compact('ris', 'supplyItems'));
    }

    /**
     * Supply saves issued_qty values and marks RIS as issued.
     * Items are deducted from Supply inventory.
     */
    public function issue(Request $request, RisRequest $ris): RedirectResponse
    {
        $this->authorizeSupply();

        if (! $ris->isPendingSupply()) {
            return back()->with('error', 'This RIS is not pending supply processing.');
        }

        $request->validate([
            'issued_qty'   => ['required', 'array'],
            'issued_qty.*' => ['required', 'integer', 'min:0'],
        ]);

        $ris->load('items');
        $supplyDept = Department::supplyHub();

        foreach ($ris->items as $risItem) {
            $qty = (int) ($request->issued_qty[$risItem->id] ?? 0);
            $risItem->update(['issued_qty' => $qty]);

            // Deduct from Supply inventory if qty > 0
            if ($qty > 0 && $supplyDept) {
                $supplyItem = Item::where('department_id', $supplyDept->id)
                    ->where('name', $risItem->item_name)
                    ->first();

                if ($supplyItem && $supplyItem->current_qty >= $qty) {
                    $supplyItem->current_qty -= $qty;
                    $supplyItem->save();
                }
            }
        }

        $ris->update([
            'status'                => 'issued',
            'supply_approved_by_id' => auth()->id(),
            'supply_approved_at'    => now(),
            'issued_by_id'          => auth()->id(),
            'issued_at'             => now(),
        ]);

        return redirect()->route('ris.supply.index')
            ->with('success', "{$ris->ris_number} issued. Awaiting acknowledgement from {$ris->requestingDept->name}.");
    }

    private function authorizeSupply(): void
    {
        $user = auth()->user();
        $supplyDept = Department::supplyHub();

        // Admin can also process supply; supply staff must be in the supply hub
        if ($user->isAdmin()) return;

        if (! $supplyDept || $user->department_id !== $supplyDept->id) {
            abort(403, 'Only Supply department staff can process RIS requests.');
        }
    }
}

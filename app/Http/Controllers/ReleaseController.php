<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;

class ReleaseController extends Controller
{
    public function index()
    {
        $scope = $this->deptScope();

        $items = Item::where('current_qty', '>', 0)
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->get();

        // Sum of pending-approval release qty per item (soft reservation)
        $reservations = Transaction::where('type', 'released')
            ->where('head_approval_status', 'pending')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->selectRaw('item_id, SUM(qty) as reserved_qty')
            ->groupBy('item_id')
            ->pluck('reserved_qty', 'item_id')
            ->toArray();

        return view('release', compact('items', 'reservations'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'item_id'            => 'required|exists:items,id',
            'qty'                => 'required|integer|min:1',
            'released_to_office' => 'required|string',
            'receiver_name'      => 'required|string',
            'date_released'      => 'required|date',
        ]);

        $item = Item::findOrFail($request->item_id);

        // Verify item belongs to user's department
        $scope = $this->deptScope();
        if ($scope && $item->department_id !== $scope) {
            abort(403, 'You cannot release items from another department.');
        }

        // Always check stock at submission time (give immediate feedback)
        if ($request->qty > $item->current_qty) {
            return back()->withErrors(['qty' => 'Quantity exceeds available stock of ' . $item->current_qty . ' ' . $item->unit])->withInput();
        }

        $user         = auth()->user();
        $autoApproved = $user->isAdmin() || $user->is_head;

        if ($autoApproved) {
            // Head / Admin: decrement inventory immediately
            $item->current_qty -= $request->qty;
            $item->save();

            Transaction::create([
                'type'                  => 'released',
                'item_id'               => $item->id,
                'item_name_snapshot'    => $item->name,
                'qty'                   => $request->qty,
                'unit'                  => $item->unit,
                'released_to_office'    => $request->released_to_office,
                'receiver_name'         => $request->receiver_name,
                'receiver_designation'  => $request->receiver_designation,
                'released_by_user_id'   => auth()->id(),
                'purpose'               => $request->purpose,
                'date_released'         => $request->date_released,
                'acknowledgment_status' => 'pending',
                'remarks'               => $request->remarks,
                'department_id'         => auth()->user()->department_id,
                'head_approval_status'  => 'approved',
                'head_approved_by_id'   => auth()->id(),
                'head_approved_at'      => now(),
            ]);

            return redirect()->route('acknowledge.index')
                ->with('success', "{$request->qty} {$item->unit} of \"{$item->name}\" released and deducted from inventory. Awaiting acknowledgment.");
        }

        // Staff: do NOT decrement — leave for head to approve
        $pendingTx = Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => $request->qty,
            'unit'                  => $item->unit,
            'released_to_office'    => $request->released_to_office,
            'receiver_name'         => $request->receiver_name,
            'receiver_designation'  => $request->receiver_designation,
            'released_by_user_id'   => auth()->id(),
            'purpose'               => $request->purpose,
            'date_released'         => $request->date_released,
            'acknowledgment_status' => 'pending',
            'remarks'               => $request->remarks,
            'department_id'         => $user->department_id,
            'head_approval_status'  => 'pending',
        ]);

        $txUrl   = route('transactions.show', $pendingTx);
        $deptId  = $user->department_id;
        $message = "{$user->name} submitted a release request for {$request->qty} {$item->unit} of \"{$item->name}\" — awaiting your approval.";
        $head    = User::where('is_head', true)->where('department_id', $deptId)->first();

        if ($head) {
            Notification::notify($head, 'tx_submitted', 'New Release Submission', $message, ['url' => $txUrl]);
        } else {
            User::where('role', 'admin')->each(
                fn ($admin) => Notification::notify($admin, 'tx_submitted', 'New Release Submission', $message, ['url' => $txUrl])
            );
        }

        return redirect()->route('release.index')
            ->with('success', 'Release submitted for head approval. Inventory will update once approved.');
    }
}

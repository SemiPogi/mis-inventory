<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ReleaseController extends Controller
{
    public function index()
    {
        $scope = $this->deptScope();
        $items = Item::where('current_qty', '>', 0)
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->get();
        return view('release', compact('items'));
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

        if ($request->qty > $item->current_qty) {
            return back()->withErrors(['qty' => 'Quantity exceeds available stock of ' . $item->current_qty . ' ' . $item->unit])->withInput();
        }

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
        ]);

        return redirect()->route('acknowledge.index')->with('success', 'Item released! Awaiting acknowledgment.');
    }
}
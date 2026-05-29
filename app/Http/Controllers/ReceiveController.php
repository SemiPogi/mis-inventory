<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Transaction;
use Illuminate\Http\Request;

class ReceiveController extends Controller
{
    public function index()
    {
        $categories = ItemCategory::active()->orderBy('name')->pluck('name');
        return view('receive', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'qty' => 'required|integer|min:1',
            'date_received' => 'required|date',
        ]);

        $deptId = auth()->user()->department_id;

        $item = Item::where('name', $request->name)
            ->where('brand', $request->brand)
            ->where('department_id', $deptId)
            ->first();

        if ($item) {
            $item->total_qty_received += $request->qty;
            $item->current_qty += $request->qty;
            $item->save();
        } else {
            $item = Item::create([
                'name'               => $request->name,
                'category'           => $request->category,
                'brand'              => $request->brand,
                'model_number'       => $request->model_number,
                'serial_number'      => $request->serial_number,
                'unit'               => $request->unit ?? 'pcs',
                'total_qty_received' => $request->qty,
                'current_qty'        => $request->qty,
                'created_by'         => auth()->id(),
                'department_id'      => $deptId,
                'expiry_date'        => $request->expiry_date ?? null,
            ]);
        }

        Transaction::create([
            'type'                  => 'received',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => $request->qty,
            'unit'                  => $item->unit,
            'received_from'         => $request->received_from,
            'ris_iar_number'        => $request->ris_iar_number,
            'date_received'         => $request->date_received,
            'received_by_user_id'   => auth()->id(),
            'remarks'               => $request->remarks,
            'acknowledgment_status' => 'acknowledged',
            'department_id'         => $deptId,
        ]);

        return redirect()->route('dashboard')->with('success', 'Item received successfully!');
    }
}
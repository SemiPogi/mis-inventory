<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemLog;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
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
            'name'          => 'required|string',
            'qty'           => 'required|integer|min:1',
            'date_received' => 'required|date',
        ]);

        $user         = auth()->user();
        $deptId       = $user->department_id;
        $autoApproved = $user->isAdmin() || $user->is_head;

        $item = Item::where('name', $request->name)
            ->where('brand', $request->brand)
            ->where('department_id', $deptId)
            ->first();

        if ($autoApproved) {
            // Head / Admin: update inventory immediately
            if ($item) {
                $qtyBefore = $item->current_qty;
                $item->total_qty_received += $request->qty;
                $item->current_qty        += $request->qty;
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
                $qtyBefore = 0;
            }

            ItemLog::record($item, 'approved_receive', $request->qty, $qtyBefore);

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
                'head_approval_status'  => 'approved',
                'head_approved_by_id'   => auth()->id(),
                'head_approved_at'      => now(),
            ]);

            return redirect()->route('dashboard')
                ->with('success', "{$request->qty} {$item->unit} of \"{$item->name}\" received and added to inventory.");
        }

        // Staff: create item with qty=0 if not yet in inventory (captures metadata),
        // then create a pending transaction — inventory updated only after head approves.
        if (! $item) {
            $item = Item::create([
                'name'               => $request->name,
                'category'           => $request->category,
                'brand'              => $request->brand,
                'model_number'       => $request->model_number,
                'serial_number'      => $request->serial_number,
                'unit'               => $request->unit ?? 'pcs',
                'total_qty_received' => 0,
                'current_qty'        => 0,
                'created_by'         => auth()->id(),
                'department_id'      => $deptId,
                'expiry_date'        => $request->expiry_date ?? null,
            ]);
        }

        $pendingTx = Transaction::create([
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
            'head_approval_status'  => 'pending',
        ]);

        $txUrl   = route('transactions.show', $pendingTx);
        $message = "{$user->name} submitted a receive request for {$request->qty} {$item->unit} of \"{$item->name}\" — awaiting your approval.";
        $head    = User::where('is_head', true)->where('department_id', $deptId)->first();

        if ($head) {
            Notification::notify($head, 'tx_submitted', 'New Receive Submission', $message, ['url' => $txUrl]);
        } else {
            User::where('role', 'admin')->each(
                fn ($admin) => Notification::notify($admin, 'tx_submitted', 'New Receive Submission', $message, ['url' => $txUrl])
            );
        }

        return redirect()->route('receive.index')
            ->with('success', 'Submitted for head approval. Inventory will update once approved.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PettyCashItem;
use App\Models\PettyCashVoucher;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PettyCashController extends Controller
{
    public function index(): View
    {
        $scope = $this->deptScope();
        $vouchers = PettyCashVoucher::with('creator')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->latest()
            ->paginate(20);

        return view('petty-cash.index', compact('vouchers'));
    }

    public function create(): View
    {
        return view('petty-cash.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'or_number'          => 'required|string|max:100',
            'store_name'         => 'required|string|max:255',
            'releasing_officer'  => 'required|string|max:255',
            'requested_amount'   => 'required|numeric|min:0.01|max:2000',
            'transport_fee'      => 'nullable|numeric|min:0',
            'date_purchased'     => 'required|date',
            'remarks'            => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.item_name'  => 'required|string|max:255',
            'items.*.qty'        => 'required|numeric|min:0.01',
            'items.*.unit'       => 'required|string|max:50',
            'items.*.unit_cost'  => 'required|numeric|min:0.01',
        ]);

        $transportFee = (float) ($data['transport_fee'] ?? 0);
        $itemsTotal   = collect($data['items'])->sum(fn($i) => $i['qty'] * $i['unit_cost']);
        $totalAmount  = $itemsTotal + $transportFee;
        $changeAmount = (float) $data['requested_amount'] - $totalAmount;

        if ($changeAmount < 0) {
            return back()
                ->withErrors(['total' => 'Total amount (₱' . number_format($totalAmount, 2) . ') exceeds the requested amount.'])
                ->withInput();
        }

        DB::transaction(function () use ($data, $transportFee, $totalAmount, $changeAmount) {
            $voucher = PettyCashVoucher::create([
                'voucher_number'    => PettyCashVoucher::generateVoucherNumber(),
                'or_number'         => $data['or_number'],
                'store_name'        => $data['store_name'],
                'releasing_officer' => $data['releasing_officer'],
                'requested_amount'  => $data['requested_amount'],
                'transport_fee'     => $transportFee,
                'total_amount'      => $totalAmount,
                'change_amount'     => $changeAmount,
                'date_purchased'    => $data['date_purchased'],
                'status'            => 'submitted',
                'created_by'        => auth()->id(),
                'remarks'           => $data['remarks'] ?? null,
                'department_id'     => auth()->user()->department_id,
            ]);

            foreach ($data['items'] as $line) {
                $qty      = (float) $line['qty'];
                $unitCost = (float) $line['unit_cost'];
                $totalCost = $qty * $unitCost;
                $itemName  = trim($line['item_name']);
                $unit      = $line['unit'];

                // Match existing item (case-insensitive) or create new
                $item = Item::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($itemName)])->first();

                if ($item) {
                    $item->increment('current_qty', $qty);
                    $item->increment('total_qty_received', $qty);
                } else {
                    $item = Item::create([
                        'name'               => $itemName,
                        'unit'               => $unit,
                        'current_qty'        => $qty,
                        'total_qty_received' => $qty,
                        'created_by'         => auth()->id(),
                    ]);
                }

                Transaction::create([
                    'type'                => 'received',
                    'item_id'             => $item->id,
                    'item_name_snapshot'  => $itemName,
                    'qty'                 => $qty,
                    'unit'                => $unit,
                    'received_from'       => $data['store_name'],
                    'ris_iar_number'      => $data['or_number'],
                    'date_received'       => $data['date_purchased'],
                    'received_by_user_id' => auth()->id(),
                ]);

                PettyCashItem::create([
                    'petty_cash_voucher_id' => $voucher->id,
                    'item_id'               => $item->id,
                    'item_name'             => $itemName,
                    'qty'                   => $qty,
                    'unit'                  => $unit,
                    'unit_cost'             => $unitCost,
                    'total_cost'            => $totalCost,
                ]);
            }
        });

        return redirect()->route('petty-cash.index')
            ->with('success', 'Voucher submitted and inventory updated.');
    }

    public function show(PettyCashVoucher $pettyCash): View
    {
        $pettyCash->load(['items.item', 'creator', 'acknowledgedBy', 'changeReturnedBy']);
        return view('petty-cash.show', compact('pettyCash'));
    }

    public function acknowledge(PettyCashVoucher $pettyCash): RedirectResponse
    {
        abort_if($pettyCash->status !== 'submitted', 422, 'Voucher is not in submitted state.');

        $pettyCash->update([
            'status'          => 'acknowledged',
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);

        return redirect()->route('petty-cash.show', $pettyCash)
            ->with('success', 'Voucher acknowledged.');
    }

    public function settle(PettyCashVoucher $pettyCash): RedirectResponse
    {
        abort_if($pettyCash->status !== 'acknowledged', 422, 'Voucher must be acknowledged first.');

        $pettyCash->update([
            'status'             => 'settled',
            'change_returned_by' => auth()->id(),
            'change_returned_at' => now(),
        ]);

        return redirect()->route('petty-cash.index')
            ->with('success', 'Voucher settled. Change return recorded.');
    }

    public function destroy(PettyCashVoucher $pettyCash): RedirectResponse
    {
        $pettyCash->delete();
        return redirect()->route('petty-cash.index')
            ->with('success', 'Voucher deleted.');
    }

    public function print(PettyCashVoucher $pettyCash): View
    {
        $pettyCash->load(['items', 'creator', 'acknowledgedBy', 'changeReturnedBy']);
        return view('petty-cash.print', compact('pettyCash'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PettyCashItem;
use App\Models\PettyCashVoucher;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', [
            'tab'     => null,
            'type'    => null,
            'rows'    => [],
            'headers' => [],
            'title'   => null,
        ]);
    }

    public function inventory(Request $request, string $type): View|Response
    {
        [$headers, $rows, $title] = match ($type) {
            'received'        => $this->receivedItems($request),
            'released'        => $this->releasedItems($request),
            'movement'        => $this->stockMovement($request),
            'snapshot'        => $this->stockSnapshot(),
            'acknowledgement' => $this->acknowledgementStatus($request),
            default           => abort(404),
        };

        if ($request->boolean('export')) {
            return $this->csvResponse($headers, $rows, $type);
        }

        return view('reports.index', compact('headers', 'rows', 'title', 'type') + ['tab' => 'inventory']);
    }

    public function pettyCash(Request $request, string $type): View|Response
    {
        [$headers, $rows, $title] = match ($type) {
            'ledger'      => $this->voucherLedger($request),
            'monthly'     => $this->monthlySummary($request),
            'outstanding' => $this->outstandingChanges(),
            'purchases'   => $this->itemPurchaseHistory($request),
            default       => abort(404),
        };

        if ($request->boolean('export')) {
            return $this->csvResponse($headers, $rows, $type);
        }

        return view('reports.index', compact('headers', 'rows', 'title', 'type') + ['tab' => 'petty-cash']);
    }

    // ── Inventory reports ──────────────────────────────────────────────────

    private function receivedItems(Request $request): array
    {
        $q = Transaction::where('type', 'received')->latest('date_received');
        if ($request->filled('from')) $q->whereDate('date_received', '>=', $request->from);
        if ($request->filled('to'))   $q->whereDate('date_received', '<=', $request->to);
        if ($request->filled('item')) $q->where('item_name_snapshot', 'like', '%' . $request->item . '%');

        $rows = $q->get()->map(fn($t) => [
            $t->date_received,
            $t->item_name_snapshot,
            $t->qty,
            $t->unit,
            $t->received_from ?? '—',
            $t->ris_iar_number ?? '—',
        ])->toArray();

        return [['Date', 'Item', 'Qty', 'Unit', 'Received From', 'RIS/IAR #'], $rows, 'Received Items'];
    }

    private function releasedItems(Request $request): array
    {
        $q = Transaction::where('type', 'released')->latest('date_released');
        if ($request->filled('from'))   $q->whereDate('date_released', '>=', $request->from);
        if ($request->filled('to'))     $q->whereDate('date_released', '<=', $request->to);
        if ($request->filled('item'))   $q->where('item_name_snapshot', 'like', '%' . $request->item . '%');
        if ($request->filled('office')) $q->where('released_to_office', 'like', '%' . $request->office . '%');

        $rows = $q->get()->map(fn($t) => [
            $t->date_released,
            $t->item_name_snapshot,
            $t->qty,
            $t->unit,
            $t->released_to_office ?? '—',
            $t->receiver_name ?? '—',
            $t->acknowledgment_status,
        ])->toArray();

        return [['Date', 'Item', 'Qty', 'Unit', 'Office', 'Receiver', 'Ack Status'], $rows, 'Released Items'];
    }

    private function stockMovement(Request $request): array
    {
        $q = Transaction::orderBy('created_at');
        if ($request->filled('item')) $q->where('item_name_snapshot', 'like', '%' . $request->item . '%');
        if ($request->filled('from')) $q->where(fn($q) =>
            $q->whereDate('date_received', '>=', $request->from)
              ->orWhereDate('date_released', '>=', $request->from));
        if ($request->filled('to')) $q->where(fn($q) =>
            $q->whereDate('date_received', '<=', $request->to)
              ->orWhereDate('date_released', '<=', $request->to));

        $rows = $q->get()->map(fn($t) => [
            $t->type === 'received' ? $t->date_received : $t->date_released,
            $t->item_name_snapshot,
            $t->type,
            $t->qty,
            $t->unit,
        ])->toArray();

        return [['Date', 'Item', 'Type', 'Qty', 'Unit'], $rows, 'Stock Movement'];
    }

    private function stockSnapshot(): array
    {
        $rows = Item::orderBy('name')->get()->map(fn($i) => [
            $i->name, $i->unit, $i->current_qty, $i->total_qty_received,
        ])->toArray();

        return [['Item', 'Unit', 'Current Qty', 'Total Received'], $rows, 'Current Stock Snapshot'];
    }

    private function acknowledgementStatus(Request $request): array
    {
        $q = Transaction::where('type', 'released');
        if ($request->filled('status')) $q->where('acknowledgment_status', $request->status);
        if ($request->filled('from'))   $q->whereDate('date_released', '>=', $request->from);
        if ($request->filled('to'))     $q->whereDate('date_released', '<=', $request->to);

        $rows = $q->latest('date_released')->get()->map(fn($t) => [
            $t->date_released,
            $t->item_name_snapshot,
            $t->qty,
            $t->released_to_office ?? '—',
            $t->receiver_name ?? '—',
            $t->acknowledgment_status,
            $t->acknowledged_date ?? '—',
        ])->toArray();

        return [
            ['Date Released', 'Item', 'Qty', 'Office', 'Receiver', 'Status', 'Ack Date'],
            $rows,
            'Acknowledgement Status',
        ];
    }

    // ── Petty cash reports ─────────────────────────────────────────────────

    private function voucherLedger(Request $request): array
    {
        $q = PettyCashVoucher::with('creator')->latest();
        if ($request->filled('from'))    $q->whereDate('date_purchased', '>=', $request->from);
        if ($request->filled('to'))      $q->whereDate('date_purchased', '<=', $request->to);
        if ($request->filled('status'))  $q->where('status', $request->status);
        if ($request->filled('officer')) $q->where('releasing_officer', 'like', '%' . $request->officer . '%');

        $rows = $q->get()->map(fn($v) => [
            $v->voucher_number,
            $v->date_purchased->format('Y-m-d'),
            $v->or_number,
            $v->store_name,
            $v->releasing_officer,
            '₱' . number_format($v->requested_amount, 2),
            '₱' . number_format($v->total_amount, 2),
            '₱' . number_format($v->change_amount, 2),
            $v->status,
            $v->creator->name,
        ])->toArray();

        return [
            ['Voucher #', 'Date', 'OR #', 'Store', 'Releasing Officer', 'Requested', 'Spent', 'Change', 'Status', 'By'],
            $rows,
            'Voucher Ledger',
        ];
    }

    private function monthlySummary(Request $request): array
    {
        $year = $request->input('year', now()->year);

        $rows = PettyCashVoucher::selectRaw('MONTH(date_purchased) as month,
                SUM(requested_amount) as total_requested,
                SUM(total_amount) as total_spent,
                SUM(transport_fee) as total_transport,
                SUM(change_amount) as total_change,
                COUNT(*) as voucher_count')
            ->whereYear('date_purchased', $year)
            ->groupByRaw('MONTH(date_purchased)')
            ->orderByRaw('MONTH(date_purchased)')
            ->get()
            ->map(fn($r) => [
                date('F', mktime(0, 0, 0, $r->month, 1)),
                $r->voucher_count,
                '₱' . number_format($r->total_requested, 2),
                '₱' . number_format($r->total_spent, 2),
                '₱' . number_format($r->total_transport, 2),
                '₱' . number_format($r->total_change, 2),
            ])->toArray();

        return [
            ['Month', 'Vouchers', 'Requested', 'Spent', 'Transport', 'Change Returned'],
            $rows,
            "Monthly Summary ($year)",
        ];
    }

    private function outstandingChanges(): array
    {
        $rows = PettyCashVoucher::whereIn('status', ['submitted', 'acknowledged'])
            ->where('change_amount', '>', 0)
            ->with('creator')
            ->latest()
            ->get()
            ->map(fn($v) => [
                $v->voucher_number,
                $v->date_purchased->format('Y-m-d'),
                $v->store_name,
                $v->releasing_officer,
                '₱' . number_format($v->change_amount, 2),
                $v->status,
                $v->creator->name,
            ])->toArray();

        return [
            ['Voucher #', 'Date', 'Store', 'Releasing Officer', 'Change Due', 'Status', 'Prepared By'],
            $rows,
            'Outstanding Changes',
        ];
    }

    private function itemPurchaseHistory(Request $request): array
    {
        $q = PettyCashItem::join('petty_cash_vouchers', 'petty_cash_items.petty_cash_voucher_id', '=', 'petty_cash_vouchers.id')
            ->orderByDesc('petty_cash_vouchers.date_purchased')
            ->select('petty_cash_items.*');

        if ($request->filled('item')) $q->where('petty_cash_items.item_name', 'like', '%' . $request->item . '%');
        if ($request->filled('from')) $q->whereDate('petty_cash_vouchers.date_purchased', '>=', $request->from);
        if ($request->filled('to'))   $q->whereDate('petty_cash_vouchers.date_purchased', '<=', $request->to);

        $rows = $q->with('voucher')->get()->map(fn($i) => [
            $i->voucher->date_purchased->format('Y-m-d'),
            $i->voucher->voucher_number,
            $i->item_name,
            $i->qty,
            $i->unit,
            '₱' . number_format($i->unit_cost, 2),
            '₱' . number_format($i->total_cost, 2),
            $i->voucher->store_name,
        ])->toArray();

        return [
            ['Date', 'Voucher #', 'Item', 'Qty', 'Unit', 'Unit Cost', 'Total', 'Store'],
            $rows,
            'Item Purchase History',
        ];
    }

    // ── CSV export ─────────────────────────────────────────────────────────

    private function csvResponse(array $headers, array $rows, string $filename): Response
    {
        $csv  = implode(',', array_map(fn($h) => '"' . $h . '"', $headers)) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', (string) ($v ?? '')) . '"', $row)) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '-' . now()->format('Ymd') . '.csv"',
        ]);
    }
}

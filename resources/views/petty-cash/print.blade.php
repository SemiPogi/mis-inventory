<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $pettyCash->voucher_number }} — Petty Cash Voucher</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; padding: 32px; }
        h1 { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        .subtitle { font-size: 11px; color: #555; margin-bottom: 16px; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin-bottom: 16px; }
        .label { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; }
        th { background: #f3f4f6; text-align: left; font-weight: 600; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; background: #f9fafb; }
        .change-row td { font-weight: bold; font-size: 14px; background: #fef3c7; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 40px; }
        .sig-block { border-top: 1px solid #111; padding-top: 4px; text-align: center; font-size: 11px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

<h1>Petty Cash Voucher</h1>
<p class="subtitle">MIS Office — La Union Medical Center</p>

<div class="grid2">
    <div><span class="label">Voucher No:</span> <strong>{{ $pettyCash->voucher_number }}</strong></div>
    <div><span class="label">Date:</span> {{ $pettyCash->date_purchased->format('F d, Y') }}</div>
    <div><span class="label">OR Number:</span> {{ $pettyCash->or_number }}</div>
    <div><span class="label">Store / Supplier:</span> {{ $pettyCash->store_name }}</div>
    <div><span class="label">Releasing Officer:</span> {{ $pettyCash->releasing_officer }}</div>
    <div><span class="label">Amount Requested:</span> ₱{{ number_format($pettyCash->requested_amount, 2) }}</div>
    @if($pettyCash->remarks)
        <div style="grid-column: span 2"><span class="label">Remarks:</span> {{ $pettyCash->remarks }}</div>
    @endif
</div>

<table>
    <thead>
        <tr>
            <th>Item</th>
            <th class="text-right">Qty</th>
            <th>Unit</th>
            <th class="text-right">Unit Cost</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($pettyCash->items as $line)
            <tr>
                <td>{{ $line->item_name }}</td>
                <td class="text-right">{{ $line->qty }}</td>
                <td>{{ $line->unit }}</td>
                <td class="text-right">₱{{ number_format($line->unit_cost, 2) }}</td>
                <td class="text-right">₱{{ number_format($line->total_cost, 2) }}</td>
            </tr>
        @endforeach
        @if($pettyCash->transport_fee > 0)
            <tr>
                <td colspan="4" style="color:#555;font-style:italic">Transport Fee</td>
                <td class="text-right">₱{{ number_format($pettyCash->transport_fee, 2) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td colspan="4" class="text-right">Total Amount Spent</td>
            <td class="text-right">₱{{ number_format($pettyCash->total_amount, 2) }}</td>
        </tr>
        <tr class="change-row">
            <td colspan="4" class="text-right">Change Returned to Accounting</td>
            <td class="text-right">₱{{ number_format($pettyCash->change_amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="signatures">
    <div class="sig-block">
        <strong>{{ $pettyCash->creator->name }}</strong><br>Prepared by
    </div>
    <div class="sig-block">
        @if($pettyCash->acknowledgedBy)
            <strong>{{ $pettyCash->acknowledgedBy->name }}</strong>
        @else
            &nbsp;
        @endif
        <br>Acknowledged by
    </div>
    <div class="sig-block">
        {{ $pettyCash->releasing_officer }}<br>Released by (Accounting)
    </div>
</div>

<p class="no-print" style="margin-top:32px; text-align:right">
    <button onclick="window.print()" style="padding:6px 16px;cursor:pointer">🖨 Print</button>
</p>

</body>
</html>

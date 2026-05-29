<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>RIS {{ $ris->ris_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #000;
            background: #fff;
            padding: 18mm 18mm 18mm 25mm; /* standard gov't paper margins */
        }

        /* ── Header ────────────────────────────────────────────────── */
        .doc-header {
            text-align: center;
            margin-bottom: 6px;
        }
        .doc-header .entity {
            font-size: 9pt;
            text-transform: uppercase;
        }
        .doc-header .form-title {
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 3px 0 1px;
        }
        .doc-header .form-code {
            font-size: 8pt;
            color: #555;
        }

        /* ── Meta row (RIS # + Fund Cluster) ───────────────────────── */
        .meta-row {
            display: flex;
            justify-content: flex-end;
            margin-top: 6px;
            margin-bottom: 4px;
            gap: 24px;
        }
        .meta-field {
            display: flex;
            align-items: baseline;
            gap: 6px;
            font-size: 9pt;
        }
        .meta-field .label { font-weight: bold; white-space: nowrap; }
        .meta-field .value {
            border-bottom: 1px solid #000;
            min-width: 120px;
            padding: 0 4px 1px;
        }

        /* ── Info block (Dept, Purpose, Date) ───────────────────────── */
        .info-block {
            border: 1px solid #000;
            margin-bottom: 0;
        }
        .info-block table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-block td {
            padding: 4px 6px;
            font-size: 9pt;
            border-right: 1px solid #000;
            vertical-align: top;
        }
        .info-block td:last-child { border-right: none; }
        .info-block .field-label {
            font-size: 7.5pt;
            color: #444;
            display: block;
            margin-bottom: 2px;
        }
        .info-block .field-value {
            font-weight: bold;
            font-size: 9.5pt;
        }

        /* ── Items table ────────────────────────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            border-top: none;
            margin-bottom: 0;
        }
        .items-table th {
            background: #f0f0f0;
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
            padding: 4px 6px;
            border: 1px solid #000;
            text-transform: uppercase;
        }
        .items-table td {
            font-size: 9pt;
            padding: 4px 6px;
            border: 1px solid #000;
            vertical-align: middle;
        }
        .items-table td.center { text-align: center; }
        .items-table .empty-row td { height: 18px; }

        /* ── Signature block ────────────────────────────────────────── */
        .sig-section {
            border: 1px solid #000;
            border-top: none;
        }
        .sig-section table {
            width: 100%;
            border-collapse: collapse;
        }
        .sig-section td {
            padding: 8px 10px 4px;
            border-right: 1px solid #000;
            vertical-align: top;
            width: 25%;
        }
        .sig-section td:last-child { border-right: none; }
        .sig-label {
            font-size: 7.5pt;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 24px; /* space for signature */
            display: block;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 2px;
            padding-top: 2px;
        }
        .sig-name {
            font-size: 9pt;
            font-weight: bold;
            text-align: center;
        }
        .sig-position {
            font-size: 8pt;
            text-align: center;
            color: #333;
        }
        .sig-date-line {
            margin-top: 4px;
            font-size: 8pt;
        }
        .sig-date-line .date-label {
            font-size: 7.5pt;
            color: #555;
            display: block;
        }
        .sig-date-line .date-value {
            border-bottom: 1px solid #000;
            display: inline-block;
            min-width: 100px;
            padding: 0 3px 1px;
        }

        /* ── Footer ─────────────────────────────────────────────────── */
        .doc-footer {
            margin-top: 6px;
            font-size: 7.5pt;
            color: #666;
            display: flex;
            justify-content: space-between;
        }

        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
            @page { size: legal portrait; margin: 18mm 18mm 18mm 25mm; }
        }
    </style>
</head>
<body>

    {{-- Print / Back buttons (hidden on print) --}}
    <div class="no-print" style="margin-bottom:12px; display:flex; gap:10px;">
        <button onclick="window.print()"
                style="padding:6px 16px; background:#2563eb; color:#fff; border:none; border-radius:6px; cursor:pointer; font-size:11pt;">
            🖨 Print
        </button>
        <button onclick="history.back()"
                style="padding:6px 16px; background:#f3f4f6; color:#111; border:1px solid #d1d5db; border-radius:6px; cursor:pointer; font-size:11pt;">
            ← Back
        </button>
    </div>

    {{-- ── Document Header ── --}}
    <div class="doc-header">
        <div class="entity">Republic of the Philippines</div>
        <div class="entity" style="font-weight:bold;">La Union Medical Center</div>
        <div class="form-title">Requisition and Issue Slip</div>
        <div class="form-code">PAS-007-95 &nbsp;|&nbsp; Appendix 63</div>
    </div>

    {{-- ── RIS # / Fund Cluster ── --}}
    <div class="meta-row">
        <div class="meta-field">
            <span class="label">RIS No.:</span>
            <span class="value">{{ $ris->ris_number }}</span>
        </div>
        <div class="meta-field">
            <span class="label">Fund Cluster:</span>
            <span class="value">&nbsp;</span>
        </div>
    </div>

    {{-- ── Department / Purpose / Date ── --}}
    <div class="info-block">
        <table>
            <tr>
                <td style="width:40%">
                    <span class="field-label">Division/Department</span>
                    <span class="field-value">{{ $ris->requestingDept->name }}</span>
                </td>
                <td style="width:35%">
                    <span class="field-label">Responsibility Center Code</span>
                    <span class="field-value">{{ $ris->requestingDept->responsibility_center_code ?? '&nbsp;' }}</span>
                </td>
                <td style="width:25%">
                    <span class="field-label">Date</span>
                    <span class="field-value">{{ $ris->created_at->format('M d, Y') }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="3" style="border-top:1px solid #000;">
                    <span class="field-label">Purpose / Justification</span>
                    <span class="field-value">{{ $ris->purpose }}</span>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Items Table ── --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:6%">Stock No.</th>
                <th style="width:6%">Unit</th>
                <th style="width:8%">Requested<br>Qty</th>
                <th style="width:8%">Issued<br>Qty</th>
                <th style="width:30%">Description</th>
                <th style="width:10%">Unit Cost</th>
                <th style="width:12%">Total Cost</th>
                <th style="width:20%">Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ris->items as $item)
                <tr>
                    <td class="center">{{ $item->stock_no ?? '' }}</td>
                    <td class="center">{{ $item->unit }}</td>
                    <td class="center">{{ $item->requested_qty }}</td>
                    <td class="center">{{ $item->issued_qty ?? '' }}</td>
                    <td>{{ $item->item_name }}</td>
                    <td class="center">&nbsp;</td>
                    <td class="center">&nbsp;</td>
                    <td>{{ $item->remarks ?? '' }}</td>
                </tr>
            @endforeach
            {{-- Pad to minimum 8 rows --}}
            @for($i = $ris->items->count(); $i < 8; $i++)
                <tr class="empty-row">
                    <td>&nbsp;</td><td></td><td></td><td></td>
                    <td></td><td></td><td></td><td></td>
                </tr>
            @endfor
        </tbody>
    </table>

    {{-- ── Signature Block (4 columns) ── --}}
    <div class="sig-section">
        <table>
            <tr>
                {{-- 1. Requested by --}}
                <td>
                    <span class="sig-label">Requested by:</span>
                    <div class="sig-line">
                        <div class="sig-name">{{ strtoupper($ris->requestedBy->name) }}</div>
                        <div class="sig-position">Requesting Officer</div>
                    </div>
                    <div class="sig-date-line">
                        <span class="date-label">Date:</span>
                        <span class="date-value">{{ $ris->created_at->format('M d, Y') }}</span>
                    </div>
                </td>

                {{-- 2. Approved by (Dept Head) --}}
                <td>
                    <span class="sig-label">Approved by:</span>
                    @if($ris->headApprovedBy)
                        <div class="sig-line">
                            <div class="sig-name">{{ strtoupper($ris->headApprovedBy->name) }}</div>
                            <div class="sig-position">Department Head</div>
                        </div>
                        <div class="sig-date-line">
                            <span class="date-label">Date:</span>
                            <span class="date-value">{{ $ris->head_approved_at?->format('M d, Y') }}</span>
                        </div>
                    @else
                        <div class="sig-line" style="margin-top:24px;"></div>
                        <div class="sig-date-line">
                            <span class="date-label">Date:</span>
                            <span class="date-value">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        </div>
                    @endif
                </td>

                {{-- 3. Issued by (Supply) --}}
                <td>
                    <span class="sig-label">Issued by:</span>
                    @if($ris->issuedBy)
                        <div class="sig-line">
                            <div class="sig-name">{{ strtoupper($ris->issuedBy->name) }}</div>
                            <div class="sig-position">Supply Officer</div>
                        </div>
                        <div class="sig-date-line">
                            <span class="date-label">Date:</span>
                            <span class="date-value">{{ $ris->issued_at?->format('M d, Y') }}</span>
                        </div>
                    @else
                        <div class="sig-line" style="margin-top:24px;"></div>
                        <div class="sig-date-line">
                            <span class="date-label">Date:</span>
                            <span class="date-value">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        </div>
                    @endif
                </td>

                {{-- 4. Received by (Dept Acknowledger) --}}
                <td>
                    <span class="sig-label">Received by:</span>
                    @if($ris->acknowledgedBy)
                        <div class="sig-line">
                            <div class="sig-name">{{ strtoupper($ris->acknowledgedBy->name) }}</div>
                            <div class="sig-position">Acknowledging Officer</div>
                        </div>
                        <div class="sig-date-line">
                            <span class="date-label">Date:</span>
                            <span class="date-value">{{ $ris->acknowledged_at?->format('M d, Y') }}</span>
                        </div>
                    @else
                        <div class="sig-line" style="margin-top:24px;"></div>
                        <div class="sig-date-line">
                            <span class="date-label">Date:</span>
                            <span class="date-value">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        </div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ── Document Footer ── --}}
    <div class="doc-footer">
        <span>Form PAS-007-95 &nbsp;|&nbsp; Appendix 63 of the Government Accounting Manual (GAM)</span>
        <span>Printed: {{ now()->format('M d, Y H:i') }}</span>
    </div>

</body>
</html>

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
            font-size: 9pt;
            color: #000;
            background: #fff;
        }

        /* ── Page wrapper ── */
        .page {
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
        }

        /* ── Letterhead header / footer ── */
        .letterhead-header img,
        .letterhead-footer img {
            width: 100%;
            display: block;
        }

        /* ── Form body (grows to fill space between header & footer) ── */
        .form-body {
            flex: 1;
            padding: 0 0.5in;
        }

        /* ── Document title ── */
        .doc-title {
            text-align: center;
            padding: 4px 0 3px;
        }
        .doc-title .sub  { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .doc-title .main { font-size: 12pt;  font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }

        /* ── Info block ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-top: 4px;
        }
        .info-table td {
            padding: 3px 6px;
            border-right: 1px solid #000;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .info-table td:last-child { border-right: none; }
        .lbl {
            font-size: 7pt;
            display: block;
            margin-bottom: 7px;
        }

        /* ── Items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            border-top: none;
            margin-top: 6px;
        }
        .items-table th {
            border: 1px solid #000;
            padding: 3px 4px;
            font-size: 8pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            background: #f5f5f5;
        }
        .items-table td {
            border: 1px solid #000;
            padding: 1px 4px;
            font-size: 8.5pt;
            height: 16px;
            vertical-align: middle;
        }
        .items-table td.c { text-align: center; }

        /* ── Purpose ── */
        .purpose-row {
            border: 1px solid #000;
            border-top: none;
            padding: 3px 6px;
            font-size: 8.5pt;
            min-height: 26px;
        }

        /* ── Signature block ── */
        .sig-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            border-top: none;
        }
        .sig-table td {
            border-right: 1px solid #000;
            padding: 4px 6px 3px;
            vertical-align: top;
            width: 25%;
            font-size: 8pt;
        }
        .sig-table td:last-child { border-right: none; }
        .sig-role  { font-size: 7.5pt; font-weight: bold; display: block; margin-bottom: 2px; }
        .sig-space { height: 28px; display: block; }
        .sig-line  { border-top: 1px solid #000; margin-top: 2px; }
        .sig-name  { font-size: 8.5pt; font-weight: bold; text-align: center; padding-top: 1px; }
        .sig-desig { font-size: 7pt; text-align: center; color: #333; }
        .sig-date  { font-size: 7.5pt; margin-top: 3px; }

        /* ── Form code line ── */
        .form-code {
            margin-top: 4px;
            margin-bottom: 2px;
            font-size: 7.5pt;
            display: flex;
            justify-content: space-between;
            padding: 0 0;
        }

        /* ── Screen controls ── */
        .no-print {
            display: flex;
            gap: 10px;
            padding: 10px 16px;
            background: #f3f4f6;
            border-bottom: 1px solid #d1d5db;
        }
        .btn-print {
            padding: 7px 20px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11pt;
            font-weight: 600;
        }
        .btn-back {
            padding: 7px 16px;
            background: #fff;
            color: #111;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11pt;
        }

        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .page { width: 100%; min-height: 100vh; }
            @page { size: letter portrait; margin: 0; }
        }
    </style>
</head>
<body>

    {{-- Screen-only controls --}}
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">🖨 Print / Save PDF</button>
        <button class="btn-back"  onclick="history.back()">← Back</button>
    </div>

    <div class="page">

        {{-- ── Official LUMC Letterhead Header ── --}}
        <div class="letterhead-header">
            <img src="{{ asset('images/Header.svg') }}" alt="La Union Medical Center"/>
        </div>

        {{-- ── Form Body ── --}}
        <div class="form-body">

            {{-- Document title --}}
            <div class="doc-title">
                <div class="sub">Property and Supply Section</div>
                <div class="main">Requisition and Issue Slip</div>
            </div>

            {{-- Info block --}}
            <table class="info-table">
                <tr>
                    <td style="width:30%">
                        <span class="lbl">Division:</span>
                        {{ $ris->requestingDept->name }}
                        <br/><br/>
                        <span class="lbl" style="margin-top:4px;">Office:</span>
                        &nbsp;
                    </td>
                    <td style="width:28%">
                        <span class="lbl">Responsibility Center Code:</span>
                        {{ $ris->requestingDept->responsibility_center_code ?? '' }}
                    </td>
                    <td style="width:22%">
                        <span class="lbl">RIS No.:</span>
                        <strong>{{ $ris->ris_number }}</strong>
                        <br/><br/>
                        <span class="lbl" style="margin-top:4px;">IAR No.:</span>
                        &nbsp;
                    </td>
                    <td style="width:20%">
                        <span class="lbl">Date:</span>
                        {{ $ris->created_at->format('M d, Y') }}
                        <br/><br/>
                        <span class="lbl" style="margin-top:4px;">Dept:</span>
                        {{ $ris->requestingDept->name }}
                    </td>
                </tr>
            </table>

            {{-- Items table --}}
            <table class="items-table">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:9%">Stock No.</th>
                        <th rowspan="2" style="width:7%">Unit</th>
                        <th rowspan="2" style="width:36%">Description</th>
                        <th style="width:10%; border-bottom:none;">Requisition</th>
                        <th style="width:10%;">Issuance</th>
                        <th rowspan="2" style="width:18%">Remark</th>
                    </tr>
                    <tr>
                        <th>Quantity</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ris->items as $item)
                        <tr>
                            <td class="c">{{ $item->stock_no ?? '' }}</td>
                            <td class="c">{{ $item->unit }}</td>
                            <td>&nbsp;{{ $item->item_name }}</td>
                            <td class="c">{{ $item->requested_qty }}</td>
                            <td class="c">{{ $item->issued_qty ?? '' }}</td>
                            <td>&nbsp;{{ $item->remarks ?? '' }}</td>
                        </tr>
                    @endforeach
                    {{-- Pad to minimum 18 rows --}}
                    @for($i = $ris->items->count(); $i < 18; $i++)
                        <tr>
                            <td>&nbsp;</td><td></td><td></td>
                            <td></td><td></td><td></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            {{-- Purpose --}}
            <div class="purpose-row">
                <strong>Purpose:</strong>&nbsp;{{ $ris->purpose }}
            </div>

            {{-- Signature block --}}
            <table class="sig-table">
                <tr>
                    <td>
                        <span class="sig-role">Requested by:</span>
                        <span class="sig-space"></span>
                        <div class="sig-line">
                            <div class="sig-name">{{ strtoupper($ris->requestedBy->name) }}</div>
                            <div class="sig-desig">Requesting Officer</div>
                        </div>
                        <div class="sig-date">Date: {{ $ris->created_at->format('M d, Y') }}</div>
                    </td>
                    <td>
                        <span class="sig-role">Approved by:</span>
                        @if($ris->headApprovedBy)
                            <span class="sig-space"></span>
                            <div class="sig-line">
                                <div class="sig-name">{{ strtoupper($ris->headApprovedBy->name) }}</div>
                                <div class="sig-desig">Department Head</div>
                            </div>
                            <div class="sig-date">Date: {{ $ris->head_approved_at?->format('M d, Y') }}</div>
                        @else
                            <span class="sig-space"></span>
                            <div class="sig-line"></div>
                            <div class="sig-date">Date: _______________</div>
                        @endif
                    </td>
                    <td>
                        <span class="sig-role">Issued by:</span>
                        @if($ris->issuedBy)
                            <span class="sig-space"></span>
                            <div class="sig-line">
                                <div class="sig-name">{{ strtoupper($ris->issuedBy->name) }}</div>
                                <div class="sig-desig">Supply Officer</div>
                            </div>
                            <div class="sig-date">Date: {{ $ris->issued_at?->format('M d, Y') }}</div>
                        @else
                            <span class="sig-space"></span>
                            <div class="sig-line"></div>
                            <div class="sig-date">Date: _______________</div>
                        @endif
                    </td>
                    <td>
                        <span class="sig-role">Received by:</span>
                        @if($ris->acknowledgedBy)
                            <span class="sig-space"></span>
                            <div class="sig-line">
                                <div class="sig-name">{{ strtoupper($ris->acknowledgedBy->name) }}</div>
                                <div class="sig-desig">Acknowledging Officer</div>
                            </div>
                            <div class="sig-date">Date: {{ $ris->acknowledged_at?->format('M d, Y') }}</div>
                        @else
                            <span class="sig-space"></span>
                            <div class="sig-line"></div>
                            <div class="sig-date">Date: _______________</div>
                        @endif
                    </td>
                </tr>
            </table>

            {{-- Form code --}}
            <div class="form-code">
                <span>PAS-007-95 &nbsp;|&nbsp; Appendix 63</span>
                <span>Status: {{ $ris->statusLabel() }} &nbsp;|&nbsp; Printed: {{ now()->format('M d, Y g:i A') }}</span>
            </div>

        </div>{{-- end .form-body --}}

        {{-- ── Official LUMC Letterhead Footer ── --}}
        <div class="letterhead-footer">
            <img src="{{ asset('images/Footer.svg') }}" alt="La Union Medical Center Footer"/>
        </div>

    </div>{{-- end .page --}}
</body>
</html>

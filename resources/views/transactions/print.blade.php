<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>
        {{ $transaction->type === 'received' ? 'Receipt Slip' : 'Release Slip' }}
        — {{ $transaction->item_name_snapshot }}
    </title>
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
            padding: 6px 0 4px;
        }
        .doc-title .sub  { font-size: 8.5pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .doc-title .main { font-size: 12pt;  font-weight: bold; letter-spacing: 1px; }

        /* ── Info block ── */
        .info-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
            margin-top: 4px;
        }
        .info-table td {
            padding: 4px 6px;
            border-right: 1px solid #000;
            font-size: 8.5pt;
            vertical-align: top;
        }
        .info-table td:last-child { border-right: none; }
        .lbl {
            font-size: 7pt;
            display: block;
            margin-bottom: 6px;
        }

        /* ── Items table ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
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
            padding: 3px 6px;
            font-size: 8.5pt;
            height: 18px;
            vertical-align: middle;
        }
        .items-table td.c { text-align: center; }

        /* ── Remarks / Purpose row ── */
        .notes-row {
            border: 1px solid #000;
            border-top: none;
            padding: 4px 6px;
            font-size: 8.5pt;
            min-height: 28px;
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
            padding: 5px 6px 4px;
            vertical-align: top;
            width: 50%;
            font-size: 8pt;
        }
        .sig-table td:last-child { border-right: none; }
        .sig-role  { font-size: 7.5pt; font-weight: bold; display: block; margin-bottom: 2px; }
        .sig-space { height: 28px; display: block; }
        .sig-line  { border-top: 1px solid #000; margin-top: 2px; }
        .sig-name  { font-size: 8.5pt; font-weight: bold; text-align: center; padding-top: 1px; }
        .sig-desig { font-size: 7pt; text-align: center; color: #444; }
        .sig-date  { font-size: 7.5pt; margin-top: 3px; }

        /* ── Status / form code line ── */
        .form-code {
            margin-top: 4px;
            margin-bottom: 2px;
            font-size: 7.5pt;
            display: flex;
            justify-content: space-between;
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

        {{-- Official LUMC Letterhead Header --}}
        <div class="letterhead-header">
            <img src="{{ asset('images/Header.svg') }}" alt="La Union Medical Center"/>
        </div>

        {{-- Form Body --}}
        <div class="form-body">

            @if($transaction->type === 'received')
                {{-- ════════════════════════════════════════════
                     RECEIVE SLIP
                ════════════════════════════════════════════ --}}

                <div class="doc-title">
                    <div class="sub">Property and Supply Section</div>
                    <div class="main">ITEM RECEIPT SLIP</div>
                </div>

                {{-- Info block --}}
                <table class="info-table">
                    <tr>
                        <td style="width:40%">
                            <span class="lbl">Division:</span>
                            {{ $transaction->department?->name ?? '—' }}
                        </td>
                        <td style="width:30%">
                            <span class="lbl">Date:</span>
                            {{ $transaction->date_received
                                ? \Carbon\Carbon::parse($transaction->date_received)->format('M d, Y')
                                : '—' }}
                        </td>
                        <td style="width:30%">
                            <span class="lbl">Ref No. (RIS/IAR):</span>
                            {{ $transaction->ris_iar_number ?? '—' }}
                        </td>
                    </tr>
                </table>

                {{-- Items table --}}
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:40%">Item</th>
                            <th style="width:10%">Qty</th>
                            <th style="width:10%">Unit</th>
                            <th style="width:40%">Received From</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $transaction->item_name_snapshot }}</td>
                            <td class="c">{{ $transaction->qty }}</td>
                            <td class="c">{{ $transaction->unit }}</td>
                            <td>{{ $transaction->received_from ?? '—' }}</td>
                        </tr>
                        {{-- Pad to minimum 8 rows --}}
                        @for($i = 1; $i < 8; $i++)
                            <tr>
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                {{-- Remarks --}}
                <div class="notes-row">
                    <strong>Remarks:</strong>&nbsp;{{ $transaction->remarks ?? '' }}
                </div>

                {{-- Signature block --}}
                <table class="sig-table">
                    <tr>
                        <td>
                            <span class="sig-role">Submitted by:</span>
                            @if($transaction->receivedBy)
                                <span class="sig-space"></span>
                                <div class="sig-line">
                                    <div class="sig-name">{{ strtoupper($transaction->receivedBy->name) }}</div>
                                </div>
                                <div class="sig-date">
                                    Date: {{ $transaction->created_at->format('M d, Y') }}
                                </div>
                            @else
                                <span class="sig-space"></span>
                                <div class="sig-line"></div>
                                <div class="sig-date">Date: _______________</div>
                            @endif
                        </td>
                        <td>
                            <span class="sig-role">Approved by:</span>
                            @if($transaction->headApprovedBy)
                                <span class="sig-space"></span>
                                <div class="sig-line">
                                    <div class="sig-name">{{ strtoupper($transaction->headApprovedBy->name) }}</div>
                                    <div class="sig-desig">Department Head</div>
                                </div>
                                <div class="sig-date">
                                    Date: {{ $transaction->head_approved_at?->format('M d, Y') ?? '—' }}
                                </div>
                            @else
                                <span class="sig-space"></span>
                                <div class="sig-line"></div>
                                <div class="sig-date">Date: _______________</div>
                            @endif
                        </td>
                    </tr>
                </table>

            @else
                {{-- ════════════════════════════════════════════
                     RELEASE SLIP
                ════════════════════════════════════════════ --}}

                <div class="doc-title">
                    <div class="sub">Property and Supply Section</div>
                    <div class="main">ITEM RELEASE SLIP</div>
                </div>

                {{-- Info block --}}
                <table class="info-table">
                    <tr>
                        <td style="width:40%">
                            <span class="lbl">Division:</span>
                            {{ $transaction->department?->name ?? '—' }}
                        </td>
                        <td style="width:30%">
                            <span class="lbl">Date:</span>
                            {{ $transaction->date_released
                                ? \Carbon\Carbon::parse($transaction->date_released)->format('M d, Y')
                                : '—' }}
                        </td>
                        <td style="width:30%">
                            <span class="lbl">Receiving Office:</span>
                            {{ $transaction->released_to_office ?? '—' }}
                        </td>
                    </tr>
                </table>

                {{-- Items table --}}
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width:35%">Item</th>
                            <th style="width:10%">Qty</th>
                            <th style="width:10%">Unit</th>
                            <th style="width:25%">Released To</th>
                            <th style="width:20%">Designation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>{{ $transaction->item_name_snapshot }}</td>
                            <td class="c">{{ $transaction->qty }}</td>
                            <td class="c">{{ $transaction->unit }}</td>
                            <td>{{ $transaction->receiver_name ?? '—' }}</td>
                            <td>{{ $transaction->receiver_designation ?? '—' }}</td>
                        </tr>
                        {{-- Pad to minimum 8 rows --}}
                        @for($i = 1; $i < 8; $i++)
                            <tr>
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                {{-- Purpose + Remarks --}}
                <div class="notes-row">
                    <strong>Purpose:</strong>&nbsp;{{ $transaction->purpose ?? '' }}
                </div>
                <div class="notes-row" style="border-top: none;">
                    <strong>Remarks:</strong>&nbsp;{{ $transaction->remarks ?? '' }}
                </div>

                {{-- Signature block --}}
                <table class="sig-table">
                    <tr>
                        <td>
                            <span class="sig-role">Released by:</span>
                            @if($transaction->releasedBy)
                                <span class="sig-space"></span>
                                <div class="sig-line">
                                    <div class="sig-name">{{ strtoupper($transaction->releasedBy->name) }}</div>
                                </div>
                                <div class="sig-date">
                                    Date: {{ $transaction->created_at->format('M d, Y') }}
                                </div>
                            @else
                                <span class="sig-space"></span>
                                <div class="sig-line"></div>
                                <div class="sig-date">Date: _______________</div>
                            @endif
                        </td>
                        <td>
                            <span class="sig-role">Approved by:</span>
                            @if($transaction->headApprovedBy)
                                <span class="sig-space"></span>
                                <div class="sig-line">
                                    <div class="sig-name">{{ strtoupper($transaction->headApprovedBy->name) }}</div>
                                    <div class="sig-desig">Department Head</div>
                                </div>
                                <div class="sig-date">
                                    Date: {{ $transaction->head_approved_at?->format('M d, Y') ?? '—' }}
                                </div>
                            @else
                                <span class="sig-space"></span>
                                <div class="sig-line"></div>
                                <div class="sig-date">Date: _______________</div>
                            @endif
                        </td>
                    </tr>
                </table>

            @endif

            {{-- Status / print footer --}}
            <div class="form-code">
                <span>Transaction #{{ $transaction->id }}</span>
                <span>
                    Status: {{ ucfirst($transaction->head_approval_status) }}
                    &nbsp;|&nbsp;
                    Printed: {{ now()->format('M d, Y g:i A') }}
                </span>
            </div>

        </div>{{-- end .form-body --}}

        {{-- Official LUMC Letterhead Footer --}}
        <div class="letterhead-footer">
            <img src="{{ asset('images/Footer.svg') }}" alt="La Union Medical Center Footer"/>
        </div>

    </div>{{-- end .page --}}
</body>
</html>

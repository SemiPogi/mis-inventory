# Group D — Print Slip Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a printable receive/release slip to each transaction, accessible via a Print button on the transaction detail page.

**Architecture:** A new `TransactionPrintController` (single `show()` method) loads the transaction with its relationships and returns a standalone print HTML view (no app layout), mirroring the existing `ris/print.blade.php` pattern. One view handles both receive and release layouts by branching on `$transaction->type`. A Print button is added to `transactions-show.blade.php`.

**Tech Stack:** Laravel 13, PHP 8.3, Blade (standalone HTML page), SQLite in-memory for tests.

---

## File Map

| Action | File | Purpose |
|--------|------|---------|
| Create | `app/Http/Controllers/TransactionPrintController.php` | Single `show()` method — scope check, eager-load relationships, return view |
| Create | `resources/views/transactions/print.blade.php` | Standalone print HTML — receive layout + release layout, same CSS shell as `ris/print.blade.php` |
| Modify | `routes/web.php` | Add `GET /transactions/{transaction}/print` route |
| Modify | `resources/views/transactions-show.blade.php` | Add Print button to the `x-slot:actions` area |
| Create | `tests/Feature/TransactionPrintTest.php` | Feature tests: 200 admin any dept, 200 own dept, 403 other dept, receive layout, release layout |

---

## Task D1: Controller + Route + Tests

**Files:**
- Create: `app/Http/Controllers/TransactionPrintController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/TransactionPrintTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/TransactionPrintTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionPrintTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D' . self::$seq,
            'is_active' => true,
        ]);
    }

    private function makeStaff(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeItem(Department $dept): Item
    {
        return Item::create([
            'name'               => 'Bond Paper ' . self::$seq,
            'unit'               => 'ream',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $dept->id,
        ]);
    }

    private function makeReceiveTx(Item $item, User $submitter): Transaction
    {
        return Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 3,
            'unit'                 => $item->unit,
            'received_from'        => 'Supply Dept',
            'ris_iar_number'       => 'IAR-001',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $submitter->id,
            'acknowledgment_status'=> 'pending',
            'head_approval_status' => 'pending',
            'department_id'        => $item->department_id,
        ]);
    }

    private function makeReleaseTx(Item $item, User $submitter): Transaction
    {
        return Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 2,
            'unit'                  => $item->unit,
            'released_to_office'    => 'Nursing Unit 3',
            'receiver_name'         => 'Maria Santos',
            'receiver_designation'  => 'Head Nurse',
            'date_released'         => now()->toDateString(),
            'released_by_user_id'   => $submitter->id,
            'purpose'               => 'For ward use',
            'acknowledgment_status' => 'pending',
            'head_approval_status'  => 'pending',
            'department_id'         => $item->department_id,
        ]);
    }

    /** @test */
    public function admin_can_print_any_transaction(): void
    {
        $dept   = $this->makeDept();
        $admin  = $this->makeAdmin();
        $staff  = $this->makeStaff($dept);
        $item   = $this->makeItem($dept);
        $tx     = $this->makeReceiveTx($item, $staff);

        $this->actingAs($admin)
            ->get(route('transactions.print', $tx))
            ->assertOk()
            ->assertSee($item->name);
    }

    /** @test */
    public function staff_can_print_own_dept_transaction(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept);
        $tx    = $this->makeReceiveTx($item, $staff);

        $this->actingAs($staff)
            ->get(route('transactions.print', $tx))
            ->assertOk()
            ->assertSee($item->name);
    }

    /** @test */
    public function staff_cannot_print_other_dept_transaction(): void
    {
        $dept1  = $this->makeDept();
        $dept2  = $this->makeDept();
        $staff1 = $this->makeStaff($dept1);
        $staff2 = $this->makeStaff($dept2);
        $item   = $this->makeItem($dept1);
        $tx     = $this->makeReceiveTx($item, $staff1);

        $this->actingAs($staff2)
            ->get(route('transactions.print', $tx))
            ->assertForbidden();
    }

    /** @test */
    public function receive_slip_shows_received_from_and_iar_number(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept);
        $tx    = $this->makeReceiveTx($item, $staff);

        $this->actingAs($staff)
            ->get(route('transactions.print', $tx))
            ->assertOk()
            ->assertSee('Supply Dept')
            ->assertSee('IAR-001')
            ->assertSee('ITEM RECEIPT SLIP');
    }

    /** @test */
    public function release_slip_shows_receiver_and_office(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept);
        $tx    = $this->makeReleaseTx($item, $staff);

        $this->actingAs($staff)
            ->get(route('transactions.print', $tx))
            ->assertOk()
            ->assertSee('Nursing Unit 3')
            ->assertSee('Maria Santos')
            ->assertSee('ITEM RELEASE SLIP');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
php artisan test tests/Feature/TransactionPrintTest.php --no-coverage
```

Expected: 5 failures — `Route [transactions.print] not defined`

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/TransactionPrintController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Transaction;

class TransactionPrintController extends Controller
{
    public function show(Transaction $transaction)
    {
        $scope = $this->deptScope();
        if ($scope && $transaction->department_id !== $scope) {
            abort(403);
        }

        $transaction->load(['item', 'department', 'receivedBy', 'releasedBy', 'headApprovedBy']);

        return view('transactions.print', compact('transaction'));
    }
}
```

- [ ] **Step 4: Add the route**

Open `routes/web.php`. Find the existing transaction routes block:

```php
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
```

Add `use App\Http\Controllers\TransactionPrintController;` in the use-statements block at the top of the file (with the other `use` lines), then add the route **after** the existing transaction routes:

```php
        Route::get('/transactions/{transaction}/print', [TransactionPrintController::class, 'show'])->name('transactions.print');
```

- [ ] **Step 5: Create a stub print view so tests can run**

Create `resources/views/transactions/print.blade.php` with minimal content (full view written in Task D2):

```blade
<!DOCTYPE html>
<html><head><title>Print</title></head>
<body>
<p>ITEM RECEIPT SLIP</p>
<p>ITEM RELEASE SLIP</p>
<p>{{ $transaction->item_name_snapshot }}</p>
<p>{{ $transaction->received_from }}</p>
<p>{{ $transaction->ris_iar_number }}</p>
<p>{{ $transaction->released_to_office }}</p>
<p>{{ $transaction->receiver_name }}</p>
</body></html>
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
php artisan test tests/Feature/TransactionPrintTest.php --no-coverage
```

Expected: 5 tests, 5 passed

- [ ] **Step 7: Run full suite to confirm no regressions**

```bash
php artisan test --no-coverage
```

Expected: all tests pass (was 145 passing before this task)

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/TransactionPrintController.php \
        routes/web.php \
        resources/views/transactions/print.blade.php \
        tests/Feature/TransactionPrintTest.php
git commit -m "feat: TransactionPrintController + route + tests (D1)"
```

---

## Task D2: Print View + Print Button

**Files:**
- Modify: `resources/views/transactions/print.blade.php` (replace stub with full layout)
- Modify: `resources/views/transactions-show.blade.php` (add Print button)

- [ ] **Step 1: Replace stub with the full print view**

Replace the entire contents of `resources/views/transactions/print.blade.php`:

```blade
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
        .doc-title .main { font-size: 12pt;  font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }

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
                    <div class="main">Item Receipt Slip</div>
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
                    <div class="main">Item Release Slip</div>
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
```

- [ ] **Step 2: Add the Print button to transactions-show.blade.php**

Open `resources/views/transactions-show.blade.php`. Find the `<x-slot:actions>` block. It currently ends with the Re-submit button just before `</x-slot:actions>`. Add the Print button **as the first item** in the actions slot (before the status badge), so it appears on the left:

Find this exact block:
```blade
        <x-slot:actions>
            {{-- Status badge --}}
            @if($transaction->isCancelled())
```

Replace with:
```blade
        <x-slot:actions>
            {{-- Print button --}}
            <a href="{{ route('transactions.print', $transaction) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold px-3 py-2 transition">
                <x-heroicon-o-printer class="w-4 h-4"/>
                Print Slip
            </a>

            {{-- Status badge --}}
            @if($transaction->isCancelled())
```

- [ ] **Step 3: Run the full test suite**

```bash
php artisan test --no-coverage
```

Expected: all tests pass (now 150 tests — was 145 before D1, gained 5 in D1)

- [ ] **Step 4: Smoke test in browser**

Navigate to `http://localhost:8000/transactions` → click any transaction → verify the "Print Slip" button appears in the top-right action area. Click it — a new tab opens showing the slip with the LUMC header/footer. Click "Print / Save PDF" to confirm the browser print dialog opens.

- [ ] **Step 5: Commit**

```bash
git add resources/views/transactions/print.blade.php \
        resources/views/transactions-show.blade.php
git commit -m "feat: printable receive/release slip with LUMC letterhead (D2)"
```

---

## Self-Review

### Spec coverage

| Spec requirement | Covered by |
|-----------------|------------|
| `GET /transactions/{transaction}/print` → `TransactionPrintController@show` | D1 — route + controller |
| Same dept scope as `TransactionController` | D1 — `$this->deptScope()` + 403 test |
| Name: `transactions.print` | D1 — route name |
| Standalone view — no app layout | D2 — plain HTML |
| Header.svg + Footer.svg letterhead | D2 — same as `ris/print.blade.php` |
| `@page { size: letter portrait; margin: 0; }` | D2 — `@media print` block |
| Screen-only print/back buttons | D2 — `.no-print` div |
| Receive slip: Division, Date, Ref No., Item, Qty, Unit, Received From, Remarks, signatures | D2 — receive `@if` block |
| Release slip: Division, Date, Item, Qty, Unit, Released To, Office, Purpose, Remarks, signatures | D2 — release `@else` block |
| "Submitted by / Approved by" for receive | D2 — `receivedBy` + `headApprovedBy` |
| "Released by / Approved by" for release | D2 — `releasedBy` + `headApprovedBy` |
| `Status: [head_approval_status] \| Printed: [now]` status line | D2 — `.form-code` div |
| Print button on `transactions-show.blade.php` | D2 — added to `x-slot:actions` |

### Placeholder scan
No TBD, TODO, or vague steps. All code is complete. ✓

### Type consistency
- `$transaction->receivedBy` — matches `Transaction::receivedBy()` BelongsTo defined in model ✓
- `$transaction->releasedBy` — matches `Transaction::releasedBy()` BelongsTo ✓
- `$transaction->headApprovedBy` — matches `Transaction::headApprovedBy()` BelongsTo ✓
- `$transaction->department` — matches `Transaction::department()` BelongsTo ✓
- Route name `transactions.print` used consistently in controller test and view button ✓

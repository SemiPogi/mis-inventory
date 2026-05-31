# Head Approval for Receive & Release — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a dept-head approval step to the Receive and Release workflows — staff submissions are held pending until a dept head or admin approves; dept heads and admins are auto-approved immediately.

**Architecture:** Add 4 columns to the existing `transactions` table (same pattern as `ris_requests`). A new `TransactionApprovalController` handles the queue page, approve, and reject actions. `ReceiveController` and `ReleaseController` branch on the submitter's role: head/admin → auto-approve and update inventory immediately; staff → create pending transaction, skip inventory change.

**Tech Stack:** Laravel 13, PHP 8.3, MySQL, Blade, Alpine.js 3, Tailwind CSS 3. No new packages.

---

## File Map

| Action | File |
|--------|------|
| Create | `database/migrations/2026_05_31_000001_add_head_approval_to_transactions_table.php` |
| Modify | `app/Models/Transaction.php` — fillable, relation, helpers |
| Modify | `app/Http/Controllers/ReceiveController.php` — conditional auto-approve vs pending |
| Modify | `app/Http/Controllers/ReleaseController.php` — conditional auto-approve vs pending |
| Create | `app/Http/Controllers/TransactionApprovalController.php` |
| Modify | `routes/web.php` — add 3 approval routes |
| Modify | `app/Http/Controllers/AcknowledgeController.php` — filter pending list to head-approved only |
| Modify | `resources/views/transactions.blade.php` — show head_approval_status badge in Status column |
| Create | `resources/views/approvals/index.blade.php` |
| Modify | `resources/views/layouts/app.blade.php` — approval badge + sidebar nav item |

---

## Task 1: Migration — add 4 columns to transactions

**Files:**
- Create: `database/migrations/2026_05_31_000001_add_head_approval_to_transactions_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('head_approval_status', ['pending', 'approved', 'rejected'])
                  ->nullable()
                  ->after('department_id');

            $table->foreignId('head_approved_by_id')
                  ->nullable()
                  ->constrained('users')
                  ->after('head_approval_status');

            $table->timestamp('head_approved_at')
                  ->nullable()
                  ->after('head_approved_by_id');

            $table->text('head_rejection_notes')
                  ->nullable()
                  ->after('head_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['head_approved_by_id']);
            $table->dropColumn([
                'head_approval_status',
                'head_approved_by_id',
                'head_approved_at',
                'head_rejection_notes',
            ]);
        });
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `Migrating: 2026_05_31_000001_add_head_approval_to_transactions_table` then `Migrated`.

- [ ] **Step 3: Verify columns exist**

```bash
php artisan tinker --execute="Schema::getColumnListing('transactions');" 2>/dev/null | grep -o 'head[^,]*'
```

Expected output includes: `head_approval_status`, `head_approved_by_id`, `head_approved_at`, `head_rejection_notes`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_31_000001_add_head_approval_to_transactions_table.php
git commit -m "feat: add head_approval_status columns to transactions table"
```

---

## Task 2: Transaction model — fillable, relation, helpers

**Files:**
- Modify: `app/Models/Transaction.php`

- [ ] **Step 1: Add to `$fillable` and add relation + helpers**

Replace the entire `app/Models/Transaction.php` with:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'type',
        'item_id',
        'item_name_snapshot',
        'qty',
        'unit',
        'received_from',
        'ris_iar_number',
        'date_received',
        'received_by_user_id',
        'released_to_office',
        'receiver_name',
        'receiver_designation',
        'released_by_user_id',
        'purpose',
        'date_released',
        'acknowledgment_status',
        'acknowledged_by_name',
        'acknowledged_date',
        'acknowledgment_remarks',
        'remarks',
        'department_id',
        // Head approval
        'head_approval_status',
        'head_approved_by_id',
        'head_approved_at',
        'head_rejection_notes',
    ];

    protected $casts = [
        'head_approved_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function headApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_approved_by_id');
    }

    /** True when waiting for head/admin action. */
    public function isPendingApproval(): bool
    {
        return $this->head_approval_status === 'pending';
    }

    /** True when approved (or legacy null — treated as pre-approved). */
    public function isApproved(): bool
    {
        return is_null($this->head_approval_status) || $this->head_approval_status === 'approved';
    }

    /** True when rejected. */
    public function isRejected(): bool
    {
        return $this->head_approval_status === 'rejected';
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Models/Transaction.php
git commit -m "feat: add headApprovedBy relation and approval helpers to Transaction model"
```

---

## Task 3: ReceiveController — conditional auto-approve vs pending

**Files:**
- Modify: `app/Http/Controllers/ReceiveController.php`

**Logic:**
- Head or Admin submits → item updated immediately, transaction saved with `head_approval_status=approved`.
- Staff submits → item created with `qty=0` if new (so item metadata is captured), transaction saved with `head_approval_status=pending`, inventory NOT updated.

- [ ] **Step 1: Replace `store()` in `ReceiveController`**

```php
public function store(Request $request)
{
    $request->validate([
        'name'          => 'required|string',
        'qty'           => 'required|integer|min:1',
        'date_received' => 'required|date',
    ]);

    $user   = auth()->user();
    $deptId = $user->department_id;
    $autoApproved = $user->isAdmin() || $user->is_head;

    $item = Item::where('name', $request->name)
        ->where('brand', $request->brand)
        ->where('department_id', $deptId)
        ->first();

    if ($autoApproved) {
        // Head / Admin: update inventory immediately
        if ($item) {
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
            'head_approval_status'  => 'approved',
            'head_approved_by_id'   => auth()->id(),
            'head_approved_at'      => now(),
        ]);

        return redirect()->route('dashboard')->with('success', 'Item received successfully!');
    }

    // Staff: create item with qty=0 if not yet in inventory, then create pending transaction
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
        'head_approval_status'  => 'pending',
    ]);

    return redirect()->route('receive.index')
        ->with('success', 'Submitted for head approval. Inventory will update once approved.');
}
```

- [ ] **Step 2: Manual smoke test — staff path**

Log in as a staff user (non-head). Submit the receive form. Verify:
1. Redirect back to `/receive` with success flash "Submitted for head approval."
2. In Tinker, `Transaction::latest()->first()->head_approval_status` = `"pending"`.
3. Item's `current_qty` has NOT changed.

- [ ] **Step 3: Manual smoke test — head/admin path**

Log in as admin or a head. Submit the receive form. Verify:
1. Redirect to `/` (dashboard) with "Item received successfully!"
2. `Transaction::latest()->first()->head_approval_status` = `"approved"`.
3. Item's `current_qty` incremented.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ReceiveController.php
git commit -m "feat: ReceiveController — auto-approve for head/admin, pending for staff"
```

---

## Task 4: ReleaseController — conditional auto-approve vs pending

**Files:**
- Modify: `app/Http/Controllers/ReleaseController.php`

**Logic:**
- Head or Admin → decrement qty immediately, transaction with `head_approval_status=approved`, `acknowledgment_status=pending`.
- Staff → do NOT decrement qty, transaction with `head_approval_status=pending`.
  - Note: staff submitting release does NOT set `acknowledgment_status=pending` yet — that's only set when the head approves.

- [ ] **Step 1: Replace `store()` in `ReleaseController`**

```php
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

    $user         = auth()->user();
    $autoApproved = $user->isAdmin() || $user->is_head;

    if ($autoApproved) {
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
            'head_approval_status'  => 'approved',
            'head_approved_by_id'   => auth()->id(),
            'head_approved_at'      => now(),
        ]);

        return redirect()->route('acknowledge.index')
            ->with('success', 'Item released! Awaiting acknowledgment.');
    }

    // Staff: check stock optimistically (reject immediately if not enough)
    if ($request->qty > $item->current_qty) {
        return back()->withErrors(['qty' => 'Quantity exceeds available stock of ' . $item->current_qty . ' ' . $item->unit])->withInput();
    }

    // Do NOT decrement — leave for approval
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
        'acknowledgment_status' => null,
        'remarks'               => $request->remarks,
        'department_id'         => auth()->user()->department_id,
        'head_approval_status'  => 'pending',
    ]);

    return redirect()->route('release.index')
        ->with('success', 'Release submitted for head approval. Inventory will update once approved.');
}
```

- [ ] **Step 2: Manual smoke test — staff path**

Log in as staff. Submit release form. Verify:
1. Redirect to `/release` with flash "Release submitted for head approval."
2. `Transaction::latest()->first()->head_approval_status` = `"pending"`.
3. `Transaction::latest()->first()->acknowledgment_status` = `null`.
4. Item `current_qty` has NOT changed.

- [ ] **Step 3: Manual smoke test — head/admin path**

Log in as head/admin. Submit release form. Verify:
1. Redirect to `/acknowledge` with "Item released! Awaiting acknowledgment."
2. `Transaction::latest()->first()->head_approval_status` = `"approved"`.
3. Item `current_qty` decremented.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ReleaseController.php
git commit -m "feat: ReleaseController — auto-approve for head/admin, pending for staff"
```

---

## Task 5: TransactionApprovalController

**Files:**
- Create: `app/Http/Controllers/TransactionApprovalController.php`

- [ ] **Step 1: Create the controller**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransactionApprovalController extends Controller
{
    public function index(): View
    {
        $user  = auth()->user();
        $this->authorizeApprover();

        $query = Transaction::with(['item', 'receivedBy', 'releasedBy', 'department'])
            ->where('head_approval_status', 'pending')
            ->when(! $user->isAdmin(), fn($q) => $q->where('department_id', $user->department_id))
            ->latest();

        $pendingReceives = (clone $query)->where('type', 'received')->get();
        $pendingReleases = (clone $query)->where('type', 'released')->get();

        return view('approvals.index', compact('pendingReceives', 'pendingReleases'));
    }

    public function approve(Transaction $transaction): RedirectResponse
    {
        $this->authorizeApproverFor($transaction);

        if (! $transaction->isPendingApproval()) {
            return back()->with('error', 'This transaction is not awaiting approval.');
        }

        $item = $transaction->item;

        if ($transaction->type === 'received') {
            $item->total_qty_received += $transaction->qty;
            $item->current_qty        += $transaction->qty;
            $item->save();
        } elseif ($transaction->type === 'released') {
            if ($item->current_qty < $transaction->qty) {
                return back()->with('error',
                    "Cannot approve: only {$item->current_qty} {$item->unit} available, but {$transaction->qty} requested.");
            }
            $item->current_qty -= $transaction->qty;
            $item->save();
        }

        $updates = [
            'head_approval_status' => 'approved',
            'head_approved_by_id'  => auth()->id(),
            'head_approved_at'     => now(),
        ];

        if ($transaction->type === 'released') {
            $updates['acknowledgment_status'] = 'pending';
        }

        $transaction->update($updates);

        return redirect()->route('approvals.index')
            ->with('success', 'Transaction approved.');
    }

    public function reject(Request $request, Transaction $transaction): RedirectResponse
    {
        $this->authorizeApproverFor($transaction);

        $request->validate([
            'notes' => ['required', 'string', 'max:500'],
        ]);

        if (! $transaction->isPendingApproval()) {
            return back()->with('error', 'This transaction is not awaiting approval.');
        }

        $transaction->update([
            'head_approval_status'  => 'rejected',
            'head_rejection_notes'  => $request->notes,
        ]);

        return redirect()->route('approvals.index')
            ->with('success', 'Transaction rejected.');
    }

    private function authorizeApprover(): void
    {
        $user = auth()->user();
        if ($user->isAdmin() || $user->is_head) return;
        abort(403, 'Only department heads and admins can manage approvals.');
    }

    private function authorizeApproverFor(Transaction $transaction): void
    {
        $user = auth()->user();
        if ($user->isAdmin()) return;
        if ($user->is_head && $user->department_id === $transaction->department_id) return;
        abort(403, 'You are not authorized to approve this transaction.');
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Http/Controllers/TransactionApprovalController.php
git commit -m "feat: add TransactionApprovalController with index, approve, reject"
```

---

## Task 6: Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Add approval routes inside the `role:admin,staff` group and import the controller**

In `routes/web.php`, add the import at the top:
```php
use App\Http\Controllers\TransactionApprovalController;
```

Inside the `Route::middleware('role:admin,staff')->group(function () {` block (the second one, after the `/create` routes), add these three routes:
```php
// Transaction approvals (dept heads + admin — controller enforces)
Route::get('/approvals', [TransactionApprovalController::class, 'index'])->name('approvals.index');
Route::patch('/approvals/{transaction}/approve', [TransactionApprovalController::class, 'approve'])->name('approvals.approve');
Route::patch('/approvals/{transaction}/reject', [TransactionApprovalController::class, 'reject'])->name('approvals.reject');
```

Add them after the IAR routes block, before the closing `});` of the `role:admin,staff` group.

- [ ] **Step 2: Verify routes are registered**

```bash
php artisan route:list --name=approvals
```

Expected output shows three routes: `GET /approvals`, `PATCH /approvals/{transaction}/approve`, `PATCH /approvals/{transaction}/reject`.

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat: add approval queue routes"
```

---

## Task 7: AcknowledgeController — filter pending list to head-approved releases only

**Files:**
- Modify: `app/Http/Controllers/AcknowledgeController.php`

The pending list should only show released transactions that are head-approved (or legacy null). Pending-head releases must not appear in the acknowledge queue.

- [ ] **Step 1: Update the `index()` method**

Replace the `$pending` query in `AcknowledgeController::index()`:

```php
$pending = Transaction::where('type', 'released')
    ->where('acknowledgment_status', 'pending')
    ->where(fn($q) => $q->whereNull('head_approval_status')
                        ->orWhere('head_approval_status', 'approved'))
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->latest()
    ->get();
```

The `$acknowledged` query stays unchanged — once something is acknowledged it was already approved.

- [ ] **Step 2: Verify behavior manually**

Submit a release as staff (creates `head_approval_status=pending`). Open `/acknowledge` — this release should NOT appear in the pending list. Approve it via `/approvals` — it should then appear in the acknowledge list.

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/AcknowledgeController.php
git commit -m "fix: exclude pending-head releases from acknowledge queue"
```

---

## Task 8: Transactions index — show head approval status badge

**Files:**
- Modify: `resources/views/transactions.blade.php`

Add a visual indicator in the Status column so users can see if their submissions are pending/rejected approval.

- [ ] **Step 1: Replace the Status column cell (currently lines ~67-75) in `transactions.blade.php`**

Find this block:
```blade
<td class="px-6 py-3">
    @if($tx->type === 'received')
        <x-status-badge status="received"/>
    @elseif($tx->acknowledgment_status === 'acknowledged')
        <x-status-badge status="acknowledged"/>
    @else
        <x-status-badge status="pending"/>
    @endif
</td>
```

Replace with:
```blade
<td class="px-6 py-3">
    @if($tx->head_approval_status === 'pending')
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-700">
            Pending Approval
        </span>
    @elseif($tx->head_approval_status === 'rejected')
        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700"
              title="{{ $tx->head_rejection_notes }}">
            Rejected
        </span>
    @elseif($tx->type === 'received')
        <x-status-badge status="received"/>
    @elseif($tx->acknowledgment_status === 'acknowledged')
        <x-status-badge status="acknowledged"/>
    @else
        <x-status-badge status="pending"/>
    @endif
</td>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/transactions.blade.php
git commit -m "feat: show head approval status badge in transactions list"
```

---

## Task 9: Approval queue view

**Files:**
- Create: `resources/views/approvals/index.blade.php`

- [ ] **Step 1: Create the directory and view file**

```bash
mkdir -p /Users/bayanestonilo/Sites/mis-inventory/resources/views/approvals
```

Create `resources/views/approvals/index.blade.php`:

```blade
<x-app-layout>
    <x-page-header title="Approvals" subtitle="Pending receive and release requests awaiting your approval"/>

    {{-- ── Pending Receives ─────────────────────────────────────── --}}
    <x-bento-card :padded="false" class="mb-6">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-heading">
                Pending Receives
                <span class="text-ink-muted">({{ $pendingReceives->count() }})</span>
            </h2>
        </div>

        @if($pendingReceives->isEmpty())
            <x-empty-state icon="inbox" title="No pending receives" hint="All receive submissions have been reviewed."/>
        @else
            {{-- Admin gets an extra Dept column --}}
            <x-table :headers="array_filter([
                'Item', 'Qty', 'Unit', 'Received From', 'Submitted By',
                auth()->user()->isAdmin() ? 'Dept' : null,
                'Date', 'Actions'
            ])">
                @foreach($pendingReceives as $tx)
                    <x-table.row x-data="{ rejectOpen: false }">
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->received_from ?? '—' }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->receivedBy?->name ?? '—' }}</td>
                        @if(auth()->user()->isAdmin())
                            <td class="px-6 py-3 text-ink-muted text-xs">{{ $tx->department?->name ?? '—' }}</td>
                        @endif
                        <td class="px-6 py-3 text-ink-body">{{ $tx->date_received }}</td>
                        <td class="px-6 py-3">
                            <div class="flex flex-col gap-2">
                                {{-- Approve --}}
                                <form method="POST" action="{{ route('approvals.approve', $tx) }}">
                                    @csrf @method('PATCH')
                                    <x-button type="submit" variant="primary" size="sm">Approve</x-button>
                                </form>

                                {{-- Reject toggle --}}
                                <x-button type="button" variant="ghost" size="sm" @click="rejectOpen = !rejectOpen">
                                    Reject
                                </x-button>

                                {{-- Inline reject form --}}
                                <div x-show="rejectOpen" x-cloak class="mt-1">
                                    <form method="POST" action="{{ route('approvals.reject', $tx) }}">
                                        @csrf @method('PATCH')
                                        <x-textarea name="notes" rows="2" required placeholder="Reason for rejection…" class="mb-2"/>
                                        <x-button type="submit" variant="danger" size="sm">Confirm Reject</x-button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>

    {{-- ── Pending Releases ─────────────────────────────────────── --}}
    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <h2 class="text-sm font-semibold text-ink-heading">
                Pending Releases
                <span class="text-ink-muted">({{ $pendingReleases->count() }})</span>
            </h2>
        </div>

        @if($pendingReleases->isEmpty())
            <x-empty-state icon="inbox" title="No pending releases" hint="All release submissions have been reviewed."/>
        @else
            <x-table :headers="array_filter([
                'Item', 'Qty', 'Unit', 'Released To', 'Office', 'Submitted By',
                auth()->user()->isAdmin() ? 'Dept' : null,
                'Date', 'Actions'
            ])">
                @foreach($pendingReleases as $tx)
                    <x-table.row x-data="{ rejectOpen: false }">
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->qty }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->unit }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->receiver_name ?? '—' }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->released_to_office ?? '—' }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $tx->releasedBy?->name ?? '—' }}</td>
                        @if(auth()->user()->isAdmin())
                            <td class="px-6 py-3 text-ink-muted text-xs">{{ $tx->department?->name ?? '—' }}</td>
                        @endif
                        <td class="px-6 py-3 text-ink-body">{{ $tx->date_released }}</td>
                        <td class="px-6 py-3">
                            <div class="flex flex-col gap-2">
                                {{-- Approve --}}
                                <form method="POST" action="{{ route('approvals.approve', $tx) }}">
                                    @csrf @method('PATCH')
                                    <x-button type="submit" variant="primary" size="sm">Approve</x-button>
                                </form>

                                {{-- Reject toggle --}}
                                <x-button type="button" variant="ghost" size="sm" @click="rejectOpen = !rejectOpen">
                                    Reject
                                </x-button>

                                {{-- Inline reject form --}}
                                <div x-show="rejectOpen" x-cloak class="mt-1">
                                    <form method="POST" action="{{ route('approvals.reject', $tx) }}">
                                        @csrf @method('PATCH')
                                        <x-textarea name="notes" rows="2" required placeholder="Reason for rejection…" class="mb-2"/>
                                        <x-button type="submit" variant="danger" size="sm">Confirm Reject</x-button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>
</x-app-layout>
```

> **Note on `x-table` with `array_filter`:** Check how `x-table` accepts headers. If it requires a plain array literal, replace the `array_filter(...)` with two separate `@if(auth()->user()->isAdmin())` blocks — one for the header and one for the data cell. The pattern used here matches the existing project style.

- [ ] **Step 2: Verify the page loads**

Log in as head or admin, navigate to `/approvals`. Should see two sections (Pending Receives, Pending Releases). If no pending items, shows empty states.

- [ ] **Step 3: Commit**

```bash
git add resources/views/approvals/index.blade.php
git commit -m "feat: add approvals queue view with inline reject"
```

---

## Task 10: Sidebar badge and nav item

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Add `$approvalBadge` to the `@php` block at the top of `app.blade.php`**

After the `$transferHeadBadge` block (around line 59), add:

```php
// Transaction approval badge (receive + release pending)
$approvalBadge = 0;
if ($user->is_head || $user->isAdmin()) {
    $approvalBadge = \App\Models\Transaction::where('head_approval_status', 'pending')
        ->when(! $user->isAdmin(), fn($q) => $q->where('department_id', $user->department_id))
        ->count();
}
```

- [ ] **Step 2: Add the Approvals nav item to the sidebar**

In the sidebar `<nav>` section, after the Transfers section and before the Assemblies or Reports section, add:

```blade
{{-- ── Approvals (dept heads + admin) ── --}}
@if($user->is_head || $user->isAdmin())
    @php $approvalsActive = request()->routeIs('approvals.*'); @endphp
    <a href="{{ route('approvals.index') }}"
       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
              {{ $approvalsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
        @if($approvalsActive)
            <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
        @endif
        <span class="relative shrink-0">
            <x-heroicon-o-clipboard-document-check class="w-5 h-5"/>
            @if($approvalBadge > 0)
                <span class="absolute -top-1 -right-1 bg-amber-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                    {{ $approvalBadge > 9 ? '9+' : $approvalBadge }}
                </span>
            @endif
        </span>
        <span x-show="!collapsed" x-transition.opacity>Approvals</span>
    </a>
@endif
```

- [ ] **Step 3: Verify sidebar shows the badge**

Log in as a dept head with pending transactions. The sidebar should show "Approvals" with an amber badge. Log in as staff — no Approvals link visible.

- [ ] **Step 4: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: add Approvals sidebar nav item with pending badge for heads and admins"
```

---

## Self-Review

### Spec coverage check

| Spec requirement | Task |
|---|---|
| 4 columns on transactions table | Task 1 |
| Transaction model fillable + helpers + relation | Task 2 |
| Staff receive → pending, no inventory change | Task 3 |
| Head/Admin receive → auto-approved, inventory updated | Task 3 |
| Staff release → pending, no qty decrement | Task 4 |
| Head/Admin release → auto-approved, qty decremented, ack=pending | Task 4 |
| GET /approvals index | Task 5, 6 |
| PATCH /approvals/{tx}/approve | Task 5, 6 |
| PATCH /approvals/{tx}/reject (notes required) | Task 5, 6 |
| Approve receive → increment item qty | Task 5 |
| Approve release → decrement item qty + ack_status=pending | Task 5 |
| Reject → no inventory change | Task 5 |
| Head scoped to own dept, Admin any dept | Tasks 5, 6 |
| AcknowledgeController filtered to head-approved only | Task 7 |
| Status badge in transactions list | Task 8 |
| Approvals queue view with inline reject | Task 9 |
| Admin gets Dept column | Task 9 |
| Sidebar badge + nav item for heads/admins | Task 10 |

All spec requirements covered. ✓

### Placeholder scan

No TBD, TODO, or "similar to Task N" references. All steps have complete code. ✓

### Type consistency check

- `$tx->isPendingApproval()` — defined in Task 2, used in Tasks 5.
- `$tx->receivedBy` / `$tx->releasedBy` / `$tx->department` — defined in Task 2, used in Task 9.
- `$tx->headApprovedBy` — defined in Task 2, not used in views (only on show page which is out of scope).
- `Transaction::where('head_approval_status', 'pending')` — consistent across Tasks 5, 7, 10.
- `auth()->user()->is_head` — boolean column on User, used in Tasks 3, 4, 5, 10. ✓
- `auth()->user()->isAdmin()` — method on User model, consistent throughout. ✓

One issue found and fixed: Task 9's `x-table` uses `array_filter()` which may not be supported. Added a note warning the implementer to check and use `@if` blocks if needed.

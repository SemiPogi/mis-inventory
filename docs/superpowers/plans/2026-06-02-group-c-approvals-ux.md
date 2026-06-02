# Group C — Approvals UX Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add three Approvals UX improvements: bulk approve with a sticky action bar (C1), stock reservation display on the release form (C2), and a RIS-creation prompt after each approval (C3).

**Architecture:** C1 adds a `bulkApprove()` controller method that loops the existing single-approve logic, and upgrades the approval view with Alpine.js checkbox management. C2 enriches `ReleaseController::index()` with a reservation query and feeds it into an updated Alpine `releaseForm()`. C3 flashes session data after `approve()` / `bulkApprove()` and renders an Alpine modal on `approvals.index`.

**Tech Stack:** Laravel 13, PHP 8.3, Alpine.js (already installed), Tailwind CSS, SQLite in-memory for tests.

---

## File Map

| Action | File |
|--------|------|
| Modify | `app/Http/Controllers/TransactionApprovalController.php` — add `bulkApprove()` (Task 1); add `suggest_ris` flash to `approve()` + `bulkApprove()` (Task 4) |
| Modify | `app/Http/Controllers/ReleaseController.php` — add `$reservations` to `index()` (Task 3) |
| Modify | `routes/web.php` — add `POST /approvals/bulk-approve` (Task 1) |
| Modify | `resources/views/approvals/index.blade.php` — checkboxes + sticky bar + warning flash (Task 2); RIS modal (Task 4) |
| Modify | `resources/views/release.blade.php` — `data-reserved`, updated Alpine `releaseForm()`, soft warning (Task 3) |
| Create | `tests/Feature/BulkApproveTest.php` |
| Create | `tests/Feature/StockReservationTest.php` |

---

### Task 1: C1 — `bulkApprove()` controller method + route + tests

**Files:**
- Create: `tests/Feature/BulkApproveTest.php`
- Modify: `app/Http/Controllers/TransactionApprovalController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create the test file**

Create `tests/Feature/BulkApproveTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkApproveTest extends TestCase
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

    private function makeStaff(Department $dept, bool $isHead = false): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => $isHead,
        ]);
    }

    private function makeItem(Department $dept, int $qty = 10): Item
    {
        return Item::create([
            'name'               => 'Widget ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    private function makeReceive(Item $item, User $submitter, array $attrs = []): Transaction
    {
        return Transaction::create(array_merge([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'pcs',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $submitter->id,
            'department_id'        => $submitter->department_id,
            'head_approval_status' => 'pending',
        ], $attrs));
    }

    private function makeRelease(Item $item, User $submitter, array $attrs = []): Transaction
    {
        return Transaction::create(array_merge([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 3,
            'unit'                  => 'pcs',
            'released_to_office'    => 'ICU',
            'receiver_name'         => 'Dr. Santos',
            'released_by_user_id'   => $submitter->id,
            'department_id'         => $submitter->department_id,
            'head_approval_status'  => 'pending',
            'acknowledgment_status' => 'pending',
        ], $attrs));
    }

    // ── Happy path ─────────────────────────────────────────────────────────

    public function test_head_can_bulk_approve_multiple_receives(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 20);
        $staff = $this->makeStaff($dept);

        $tx1 = $this->makeReceive($item, $staff);
        $tx2 = $this->makeReceive($item, $staff);

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx1->id, $tx2->id]])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('transactions', ['id' => $tx1->id, 'head_approval_status' => 'approved']);
        $this->assertDatabaseHas('transactions', ['id' => $tx2->id, 'head_approval_status' => 'approved']);
    }

    public function test_bulk_approve_updates_item_qty(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);
        $staff = $this->makeStaff($dept);
        $tx    = $this->makeReceive($item, $staff, ['qty' => 5]);

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx->id]]);

        $this->assertEquals(5, $item->fresh()->current_qty);
    }

    public function test_bulk_approve_sends_notification_per_transaction(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 20);
        $staff = $this->makeStaff($dept);

        $tx1 = $this->makeReceive($item, $staff);
        $tx2 = $this->makeReceive($item, $staff);

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx1->id, $tx2->id]]);

        $this->assertEquals(
            2,
            Notification::where('user_id', $staff->id)->where('type', 'tx_approved_receive')->count()
        );
    }

    // ── Partial failure ────────────────────────────────────────────────────

    public function test_bulk_approve_partial_failure_approves_passing_ones_and_warns(): void
    {
        $dept   = $this->makeDept();
        $head   = $this->makeStaff($dept, isHead: true);
        $item   = $this->makeItem($dept, 5);     // only 5 in stock
        $staff  = $this->makeStaff($dept);

        $goodTx = $this->makeReceive($item, $staff, ['qty' => 2]);
        $badTx  = $this->makeRelease($item, $staff, ['qty' => 10]); // exceeds stock

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$goodTx->id, $badTx->id]])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('success')
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('transactions', ['id' => $goodTx->id, 'head_approval_status' => 'approved']);
        $this->assertDatabaseHas('transactions', ['id' => $badTx->id,  'head_approval_status' => 'pending']);
    }

    public function test_bulk_approve_head_cannot_approve_other_dept_transactions(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $head1 = $this->makeStaff($dept1, isHead: true);
        $item  = $this->makeItem($dept2, 10);
        $tx    = $this->makeReceive($item, $this->makeStaff($dept2));

        $this->actingAs($head1)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx->id]])
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('warning');

        $this->assertDatabaseHas('transactions', ['id' => $tx->id, 'head_approval_status' => 'pending']);
    }

    // ── Validation & authorization ─────────────────────────────────────────

    public function test_bulk_approve_requires_ids(): void
    {
        $dept = $this->makeDept();
        $head = $this->makeStaff($dept, isHead: true);

        $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), [])
            ->assertSessionHasErrors('ids');
    }

    public function test_staff_cannot_access_bulk_approve(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, 10);
        $tx    = $this->makeReceive($item, $staff);

        $this->actingAs($staff)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx->id]])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
php artisan test tests/Feature/BulkApproveTest.php --stop-on-failure
```

Expected: FAIL — route `approvals.bulk-approve` does not exist.

- [ ] **Step 3: Add the route**

In `routes/web.php`, find the approvals block (around line 137) and add the new route:

```php
        Route::get('/approvals', [TransactionApprovalController::class, 'index'])->name('approvals.index');
        Route::patch('/approvals/{transaction}/approve', [TransactionApprovalController::class, 'approve'])->name('approvals.approve');
        Route::patch('/approvals/{transaction}/reject', [TransactionApprovalController::class, 'reject'])->name('approvals.reject');
        Route::post('/approvals/bulk-approve', [TransactionApprovalController::class, 'bulkApprove'])->name('approvals.bulk-approve');
```

- [ ] **Step 4: Add `bulkApprove()` to `TransactionApprovalController`**

Add the `bulkApprove()` method after `reject()` (before `authorizeApprover()`):

```php
    public function bulkApprove(Request $request): RedirectResponse
    {
        $this->authorizeApprover();

        $request->validate([
            'ids'   => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:transactions,id'],
        ]);

        $user     = auth()->user();
        $approved = 0;
        $failed   = [];

        foreach ($request->ids as $id) {
            $transaction = Transaction::with(['item', 'department'])->find($id);

            if (! $transaction || ! $transaction->isPendingApproval()) {
                $failed[] = "Transaction #{$id}: not pending";
                continue;
            }

            // Scope check (mirrors authorizeApproverFor)
            if (! $user->isAdmin() && ! ($user->is_head && $user->department_id === $transaction->department_id)) {
                $failed[] = "\"{$transaction->item_name_snapshot}\": not in your scope";
                continue;
            }

            $item = $transaction->item;

            if ($transaction->type === 'received') {
                $item->total_qty_received += $transaction->qty;
                $item->current_qty        += $transaction->qty;
                $item->save();
            } elseif ($transaction->type === 'released') {
                if ($item->current_qty < $transaction->qty) {
                    $failed[] = "\"{$transaction->item_name_snapshot}\": insufficient stock ({$item->current_qty} {$item->unit} available)";
                    continue;
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

            // Notify submitter (same pattern as single approve)
            $submitterId = $transaction->type === 'received'
                ? $transaction->received_by_user_id
                : $transaction->released_by_user_id;

            $notifType  = $transaction->type === 'received' ? 'tx_approved_receive' : 'tx_approved_release';
            $notifTitle = $transaction->type === 'received'
                ? 'Receive Approved — Collect from Supply'
                : 'Release Approved';
            $notifBody  = $transaction->type === 'received'
                ? "Your receive request for {$transaction->qty} {$transaction->unit} of \"{$transaction->item_name_snapshot}\" was approved. Items have been added to inventory. Please collect from the Supply Department."
                : "Your release request for {$transaction->qty} {$transaction->unit} of \"{$transaction->item_name_snapshot}\" was approved and inventory has been updated.";

            if ($submitterId) {
                Notification::notify(
                    $submitterId,
                    $notifType,
                    $notifTitle,
                    $notifBody,
                    ['url' => route('transactions.show', $transaction)]
                );
            }

            $approved++;
        }

        $redirect = redirect()->route('approvals.index');

        if ($approved > 0) {
            $word = $approved === 1 ? 'transaction' : 'transactions';
            $redirect = $redirect->with('success', "{$approved} {$word} approved successfully.");
        }

        if (! empty($failed)) {
            $redirect = $redirect->with('warning', 'Some items could not be approved: ' . implode('; ', $failed));
        }

        return $redirect;
    }
```

Note: The notification logic is duplicated intentionally (same as `approve()`) to keep `bulkApprove()` self-contained.

- [ ] **Step 5: Run the tests**

```bash
php artisan test tests/Feature/BulkApproveTest.php
```

Expected: all tests PASS.

- [ ] **Step 6: Run the full suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/TransactionApprovalController.php \
        routes/web.php \
        tests/Feature/BulkApproveTest.php
git commit -m "$(cat <<'EOF'
feat: bulk approve transactions with per-item notifications (C1)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: C1 — Bulk approve UI (checkboxes + sticky bar + warning flash)

**Files:**
- Modify: `resources/views/approvals/index.blade.php`

No new tests — this is a view-only change. Verify manually by running `php artisan test` to confirm no regressions.

- [ ] **Step 1: Replace `approvals/index.blade.php` with the updated version**

The complete new content for `resources/views/approvals/index.blade.php`:

```blade
<x-app-layout>
    <x-page-header title="Approvals" subtitle="Pending receive and release requests awaiting your approval"/>

    @if(session('success'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
            <x-heroicon-o-check-circle class="w-5 h-5 shrink-0 text-emerald-500"/>
            {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <x-heroicon-o-exclamation-triangle class="w-5 h-5 shrink-0 text-amber-500"/>
            {{ session('warning') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 flex items-center gap-2 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <x-heroicon-o-x-circle class="w-5 h-5 shrink-0 text-red-500"/>
            {{ session('error') }}
        </div>
    @endif

    {{-- Action guide --}}
    @if($pendingReceives->isNotEmpty() || $pendingReleases->isNotEmpty())
        <div class="mb-5 flex items-start gap-3 rounded-xl border border-violet-200 bg-violet-50 px-4 py-3 text-sm text-violet-800">
            <x-heroicon-o-shield-check class="w-5 h-5 mt-0.5 shrink-0 text-violet-500"/>
            <div>
                <p class="font-medium">Review carefully before approving</p>
                <p class="text-violet-700 mt-0.5">
                    <strong>Approving a Receive</strong> adds items to inventory immediately. &nbsp;
                    <strong>Approving a Release</strong> deducts stock and starts the acknowledgment flow. &nbsp;
                    Rejected submissions leave inventory unchanged.
                </p>
            </div>
        </div>
    @endif

    {{-- Bulk-approve form (hidden; inputs injected by Alpine) --}}
    <form x-ref="bulkForm" method="POST" action="{{ route('approvals.bulk-approve') }}" class="hidden">
        @csrf
        <template x-for="id in selected" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>
    </form>

    <div x-data="approvalManager()">

    {{-- ── Pending Receives ─────────────────────────────────────── --}}
    @php $receiveIds = $pendingReceives->pluck('id')->all(); @endphp
    <x-bento-card :padded="false" class="mb-6">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">
                Pending Receives
                <span class="text-ink-muted">({{ $pendingReceives->count() }})</span>
            </h2>
        </div>

        @if($pendingReceives->isEmpty())
            <x-empty-state icon="inbox" title="No pending receives" hint="All receive submissions have been reviewed."/>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-page/50">
                        <tr class="border-b border-surface-border">
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                       :checked="allSelected({{ json_encode($receiveIds) }})"
                                       @change="$event.target.checked ? selectAll({{ json_encode($receiveIds) }}) : deselectAll({{ json_encode($receiveIds) }})">
                            </th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Item</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Qty</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Unit</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Received From</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Submitted By</th>
                            @if(auth()->user()->isAdmin())
                                <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Dept</th>
                            @endif
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Date</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @foreach($pendingReceives as $tx)
                            <tr x-data="{ rejectOpen: false }">
                                <td class="px-4 py-3 w-10">
                                    <input type="checkbox"
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                           :checked="isSelected({{ $tx->id }})"
                                           @change="toggle({{ $tx->id }})">
                                </td>
                                <td class="px-6 py-3 font-medium text-ink-heading">{{ $tx->item_name_snapshot }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->qty }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->unit }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->received_from ?? '—' }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->receivedBy?->name ?? '—' }}</td>
                                @if(auth()->user()->isAdmin())
                                    <td class="px-6 py-3 text-ink-muted text-xs">{{ $tx->department?->name ?? '—' }}</td>
                                @endif
                                <td class="px-6 py-3 text-ink-body">{{ $tx->date_received }}</td>
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2 min-w-[120px]">
                                        {{-- Approve --}}
                                        <form method="POST" action="{{ route('approvals.approve', $tx) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="primary" class="w-full">Approve</x-button>
                                        </form>

                                        {{-- Reject toggle --}}
                                        <x-button type="button" variant="ghost" class="w-full" @click="rejectOpen = !rejectOpen">
                                            Reject
                                        </x-button>

                                        {{-- Inline reject form --}}
                                        <div x-show="rejectOpen" x-cloak class="mt-1">
                                            <form method="POST" action="{{ route('approvals.reject', $tx) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-textarea name="notes" rows="2" required placeholder="Reason for rejection…" class="mb-2 w-full"/>
                                                <x-button type="submit" variant="danger" class="w-full">Confirm Reject</x-button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-bento-card>

    {{-- ── Pending Releases ─────────────────────────────────────── --}}
    @php $releaseIds = $pendingReleases->pluck('id')->all(); @endphp
    <x-bento-card :padded="false">
        <div class="px-6 py-4 border-b border-surface-border">
            <h2 class="text-sm font-semibold text-ink-heading">
                Pending Releases
                <span class="text-ink-muted">({{ $pendingReleases->count() }})</span>
            </h2>
        </div>

        @if($pendingReleases->isEmpty())
            <x-empty-state icon="inbox" title="No pending releases" hint="All release submissions have been reviewed."/>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-page/50">
                        <tr class="border-b border-surface-border">
                            <th class="px-4 py-3 w-10">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                       :checked="allSelected({{ json_encode($releaseIds) }})"
                                       @change="$event.target.checked ? selectAll({{ json_encode($releaseIds) }}) : deselectAll({{ json_encode($releaseIds) }})">
                            </th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Item</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Qty</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Unit</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Released To</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Office</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Submitted By</th>
                            @if(auth()->user()->isAdmin())
                                <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Dept</th>
                            @endif
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Date</th>
                            <th class="text-left px-6 py-3 text-xs font-medium text-ink-muted uppercase tracking-wide">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @foreach($pendingReleases as $tx)
                            <tr x-data="{ rejectOpen: false }">
                                <td class="px-4 py-3 w-10">
                                    <input type="checkbox"
                                           class="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                                           :checked="isSelected({{ $tx->id }})"
                                           @change="toggle({{ $tx->id }})">
                                </td>
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
                                <td class="px-6 py-4">
                                    <div class="flex flex-col gap-2 min-w-[120px]">
                                        {{-- Approve --}}
                                        <form method="POST" action="{{ route('approvals.approve', $tx) }}">
                                            @csrf
                                            @method('PATCH')
                                            <x-button type="submit" variant="primary" class="w-full">Approve</x-button>
                                        </form>

                                        {{-- Reject toggle --}}
                                        <x-button type="button" variant="ghost" class="w-full" @click="rejectOpen = !rejectOpen">
                                            Reject
                                        </x-button>

                                        {{-- Inline reject form --}}
                                        <div x-show="rejectOpen" x-cloak class="mt-1">
                                            <form method="POST" action="{{ route('approvals.reject', $tx) }}">
                                                @csrf
                                                @method('PATCH')
                                                <x-textarea name="notes" rows="2" required placeholder="Reason for rejection…" class="mb-2 w-full"/>
                                                <x-button type="submit" variant="danger" class="w-full">Confirm Reject</x-button>
                                            </form>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-bento-card>

    {{-- Sticky action bar — appears when ≥1 checkbox is checked --}}
    <div x-show="selected.length > 0"
         x-cloak
         class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-surface-border shadow-lg px-6 py-4 flex items-center gap-4">
        <span class="text-sm text-ink-body">
            <span x-text="selected.length"></span> selected
        </span>
        <x-button variant="primary" @click="$refs.bulkForm.submit()">
            Approve Selected (<span x-text="selected.length"></span>)
        </x-button>
    </div>

    </div>{{-- end x-data="approvalManager()" --}}

    <script>
        function approvalManager() {
            return {
                selected: [],
                toggle(id) {
                    const idx = this.selected.indexOf(id);
                    idx === -1 ? this.selected.push(id) : this.selected.splice(idx, 1);
                },
                selectAll(ids) {
                    ids.forEach(id => {
                        if (!this.selected.includes(id)) this.selected.push(id);
                    });
                },
                deselectAll(ids) {
                    this.selected = this.selected.filter(id => !ids.includes(id));
                },
                isSelected(id) {
                    return this.selected.includes(id);
                },
                allSelected(ids) {
                    return ids.length > 0 && ids.every(id => this.selected.includes(id));
                },
            }
        }
    </script>
</x-app-layout>
```

- [ ] **Step 2: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass (no PHP errors introduced).

- [ ] **Step 3: Commit**

```bash
git add resources/views/approvals/index.blade.php
git commit -m "$(cat <<'EOF'
feat: bulk approve UI — checkboxes, select-all, sticky action bar, warning flash (C1)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: C2 — Stock reservation in ReleaseController + release form

**Files:**
- Create: `tests/Feature/StockReservationTest.php`
- Modify: `app/Http/Controllers/ReleaseController.php`
- Modify: `resources/views/release.blade.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/StockReservationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockReservationTest extends TestCase
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

    private function makeStaff(Department $dept, bool $isHead = false): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => $isHead,
        ]);
    }

    private function makeItem(Department $dept, int $qty = 10): Item
    {
        return Item::create([
            'name'               => 'Widget ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    public function test_release_index_includes_reservations_for_pending_releases(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, 20);

        // Create a pending release — this should be counted as a reservation
        Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 7,
            'unit'                  => 'pcs',
            'released_to_office'    => 'ICU',
            'receiver_name'         => 'Dr. Santos',
            'released_by_user_id'   => $staff->id,
            'department_id'         => $dept->id,
            'head_approval_status'  => 'pending',
            'acknowledgment_status' => 'pending',
        ]);

        $response = $this->actingAs($staff)->get(route('release.index'));

        $response->assertViewHas('reservations', function ($reservations) use ($item) {
            return isset($reservations[$item->id]) && (int) $reservations[$item->id] === 7;
        });
    }

    public function test_release_index_excludes_approved_releases_from_reservations(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, 20);

        // An approved release should NOT be a reservation
        Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 5,
            'unit'                  => 'pcs',
            'released_to_office'    => 'ICU',
            'receiver_name'         => 'Dr. Santos',
            'released_by_user_id'   => $staff->id,
            'department_id'         => $dept->id,
            'head_approval_status'  => 'approved',
            'acknowledgment_status' => 'pending',
            'head_approved_by_id'   => $staff->id,
            'head_approved_at'      => now(),
        ]);

        $response = $this->actingAs($staff)->get(route('release.index'));

        $response->assertViewHas('reservations', function ($reservations) use ($item) {
            return ! isset($reservations[$item->id]) || (int) $reservations[$item->id] === 0;
        });
    }
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
php artisan test tests/Feature/StockReservationTest.php --stop-on-failure
```

Expected: FAIL — view does not receive `reservations`.

- [ ] **Step 3: Update `ReleaseController::index()` to compute reservations**

Replace the `index()` method in `app/Http/Controllers/ReleaseController.php`:

```php
    public function index()
    {
        $scope = $this->deptScope();

        $items = Item::where('current_qty', '>', 0)
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->get();

        // Sum of pending-approval release qty per item (soft reservation)
        $reservations = Transaction::where('type', 'released')
            ->where('head_approval_status', 'pending')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->selectRaw('item_id, SUM(qty) as reserved_qty')
            ->groupBy('item_id')
            ->pluck('reserved_qty', 'item_id');

        return view('release', compact('items', 'reservations'));
    }
```

- [ ] **Step 4: Run the tests to confirm they pass**

```bash
php artisan test tests/Feature/StockReservationTest.php --stop-on-failure
```

Expected: PASS.

- [ ] **Step 5: Update `release.blade.php`**

Three changes to `resources/views/release.blade.php`:

**Change A — add `data-reserved` to each item option and show reserved count in text.**

Find the item `@foreach` loop (around line 58) and replace it:

```blade
                        @foreach($items as $item)
                            <option value="{{ $item->id }}"
                                    data-qty="{{ $item->current_qty }}"
                                    data-unit="{{ $item->unit }}"
                                    data-reserved="{{ $reservations[$item->id] ?? 0 }}"
                                    @selected(old('item_id', request('item_id')) == $item->id)>
                                {{ $item->name }}{{ $item->brand ? ' — '.$item->brand : '' }}
                                ({{ $item->current_qty }} {{ $item->unit }} available
                                @if(($reservations[$item->id] ?? 0) > 0), {{ $reservations[$item->id] }} reserved@endif)
                            </option>
                        @endforeach
```

**Change B — add a soft-warning div beneath the Available Qty input.**

Find the "Available Qty" field block (around line 70) and replace it:

```blade
                <div>
                    <x-label>Available Qty</x-label>
                    <x-input readonly x-model="availableLabel" class="bg-surface-page text-ink-muted"/>
                    <p x-show="overReservation"
                       x-cloak
                       class="mt-1 text-xs text-amber-700">
                        Note: <span x-text="reserved"></span> <span x-text="unit"></span> are pending approval. Releasing may exceed available stock if those are approved first.
                    </p>
                </div>
```

**Change C — update the `releaseForm()` Alpine function** (replace the entire `<script>` block at the bottom):

```blade
    <script>
        function releaseForm() {
            return {
                itemId: '{{ old('item_id', request()->query('item_id', '')) }}',
                qty: {{ (int) old('qty', request()->query('qty', 0)) }},
                available: 0,
                reserved: 0,
                unit: '',
                confirming: false,
                get availableLabel() {
                    if (!this.available) return '';
                    return this.reserved > 0
                        ? `${this.available} ${this.unit} available / ${this.reserved} pending approval`
                        : `${this.available} ${this.unit} available`;
                },
                get overReservation() {
                    return this.reserved > 0 && this.qty > (this.available - this.reserved);
                },
                init() {
                    if (this.itemId) this.refreshAvailable();
                },
                onItemChange(e) {
                    this.refreshAvailable(e.target);
                },
                refreshAvailable(selectEl) {
                    selectEl = selectEl || document.getElementById('item_id');
                    const opt = selectEl.options[selectEl.selectedIndex];
                    this.available = parseInt(opt?.dataset.qty || 0, 10);
                    this.reserved  = parseInt(opt?.dataset.reserved || 0, 10);
                    this.unit = opt?.dataset.unit || '';
                },
                onSubmit(e) {
                    if (this.available > 0 && this.qty > this.available * 0.5 && !this.confirming) {
                        this.confirming = true;
                        return;
                    }
                    e.target.submit();
                },
                confirm() {
                    this.confirming = false;
                    document.querySelector('form').submit();
                },
            }
        }
    </script>
```

- [ ] **Step 6: Run the full suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ReleaseController.php \
        resources/views/release.blade.php \
        tests/Feature/StockReservationTest.php
git commit -m "$(cat <<'EOF'
feat: show pending-approval reservation on release form with soft warning (C2)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: C3 — RIS prompt after approval (session flash + modal)

**Files:**
- Modify: `app/Http/Controllers/TransactionApprovalController.php`
- Modify: `resources/views/approvals/index.blade.php`

- [ ] **Step 1: Write the failing test**

Add a new test file `tests/Feature/RisPromptTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RisPromptTest extends TestCase
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

    private function makeStaff(Department $dept, bool $isHead = false): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => $isHead,
        ]);
    }

    private function makeItem(Department $dept, int $qty = 10): Item
    {
        return Item::create([
            'name'               => 'Bond Paper A4',
            'unit'               => 'ream',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    public function test_approving_receive_flashes_suggest_ris(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);
        $staff = $this->makeStaff($dept);

        $tx = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'ream',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $this->actingAs($head)
            ->patch(route('approvals.approve', $tx))
            ->assertRedirect(route('approvals.index'))
            ->assertSessionHas('suggest_ris');
    }

    public function test_suggest_ris_flash_contains_transaction_details(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);
        $staff = $this->makeStaff($dept);

        $tx = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'ream',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $response = $this->actingAs($head)->patch(route('approvals.approve', $tx));

        $risData = $response->getSession()->get('suggest_ris');
        $this->assertEquals('Bond Paper A4', $risData['item']);
        $this->assertEquals(5, $risData['qty']);
        $this->assertEquals('ream', $risData['unit']);
        $this->assertEquals('received', $risData['type']);
        $this->assertEquals($dept->id, $risData['dept_id']);
    }

    public function test_bulk_approve_flashes_suggest_ris_with_count(): void
    {
        $dept  = $this->makeDept();
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);
        $staff = $this->makeStaff($dept);

        $tx1 = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 3,
            'unit'                 => 'ream',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);
        $tx2 = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 2,
            'unit'                 => 'ream',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $response = $this->actingAs($head)
            ->post(route('approvals.bulk-approve'), ['ids' => [$tx1->id, $tx2->id]])
            ->assertSessionHas('suggest_ris');

        $risData = $response->getSession()->get('suggest_ris');
        $this->assertTrue($risData['bulk'] ?? false);
        $this->assertEquals(2, $risData['count']);
    }
}
```

- [ ] **Step 2: Run the tests to confirm they fail**

```bash
php artisan test tests/Feature/RisPromptTest.php --stop-on-failure
```

Expected: FAIL — `suggest_ris` key not in session.

- [ ] **Step 3: Add `session()->flash('suggest_ris', ...)` to `approve()`**

In `app/Http/Controllers/TransactionApprovalController.php`, find `approve()` and add the flash call after `$transaction->update($updates)` and after the notification block, immediately before `$successMsg =`:

```php
        if ($submitterId) {
            Notification::notify(
                $submitterId,
                $notifType,
                $notifTitle,
                $notifBody,
                ['url' => route('transactions.show', $transaction)]
            );
        }

        session()->flash('suggest_ris', [
            'item'    => $transaction->item_name_snapshot,
            'qty'     => $transaction->qty,
            'unit'    => $transaction->unit,
            'type'    => $transaction->type,
            'dept'    => $transaction->department?->name,
            'dept_id' => $transaction->department_id,
        ]);

        $successMsg = $transaction->type === 'received'
```

- [ ] **Step 4: Add `session()->flash('suggest_ris', ...)` to `bulkApprove()`**

In `bulkApprove()` (from Task 1), find the `if ($approved > 0)` block and add the flash call as the first statement inside it:

```php
        if ($approved > 0) {
            session()->flash('suggest_ris', [
                'bulk'  => true,
                'count' => $approved,
            ]);
            $word = $approved === 1 ? 'transaction' : 'transactions';
            $redirect = $redirect->with('success', "{$approved} {$word} approved successfully.");
        }
```

- [ ] **Step 5: Run the tests to confirm they pass**

```bash
php artisan test tests/Feature/RisPromptTest.php --stop-on-failure
```

Expected: PASS.

- [ ] **Step 6: Add the RIS prompt modal to `approvals/index.blade.php`**

Insert the following block at the very end of the file, just before `</x-app-layout>`:

```blade
    {{-- ── RIS Prompt Modal ─────────────────────────────────────── --}}
    @if(session('suggest_ris'))
        @php $risData = session('suggest_ris') @endphp
        <div x-data="{ open: true }"
             x-show="open"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div class="bg-surface-tile rounded-2xl shadow-tile-hover p-6 max-w-md mx-4 animate-pop">
                <h3 class="text-base font-semibold text-ink-heading mb-3">Create a RIS for this transaction?</h3>

                @if(!($risData['bulk'] ?? false))
                    <p class="text-sm text-ink-body mb-1">
                        You just approved:
                        <strong>{{ $risData['qty'] }} {{ $risData['unit'] }} of {{ $risData['item'] }}</strong>
                    </p>
                    <p class="text-sm text-ink-muted mb-4">
                        ({{ ucfirst($risData['type']) }} — {{ $risData['dept'] ?? '—' }})
                    </p>
                    <p class="text-sm text-ink-body mb-5">
                        Would you like to open the RIS form pre-filled with this transaction's details?
                    </p>
                @else
                    <p class="text-sm text-ink-body mb-5">
                        You approved <strong>{{ $risData['count'] }}</strong>
                        {{ $risData['count'] === 1 ? 'transaction' : 'transactions' }}.
                        Would you like to create a RIS?
                    </p>
                @endif

                <div class="flex justify-end gap-3">
                    <x-button type="button" variant="ghost" @click="open = false">Not Now</x-button>

                    @if(!($risData['bulk'] ?? false))
                        <x-button as="a" variant="primary"
                            href="{{ route('ris.create', array_filter([
                                'purpose'       => ucfirst($risData['type']).' '.$risData['qty'].' '.$risData['unit'].' of '.$risData['item'],
                                'department_id' => $risData['dept_id'],
                            ])) }}">
                            Yes, Create RIS →
                        </x-button>
                    @else
                        <x-button as="a" variant="primary" href="{{ route('ris.create') }}">
                            Yes, Create RIS →
                        </x-button>
                    @endif
                </div>
            </div>
        </div>
    @endif
```

- [ ] **Step 7: Run the full suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/TransactionApprovalController.php \
        resources/views/approvals/index.blade.php \
        tests/Feature/RisPromptTest.php
git commit -m "$(cat <<'EOF'
feat: show RIS creation prompt after approving transactions (C3)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

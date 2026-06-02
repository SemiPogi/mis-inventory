# Group B — Notifications Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fire in-app notifications at 4 key moments in the receive/release approval workflow so submitters and approvers are kept informed without polling.

**Architecture:** `Notification::notify()` static factory calls are inserted directly into the 3 controllers that trigger state changes (ReceiveController, ReleaseController, TransactionApprovalController). Notifications are synchronous — no jobs or queues. No new controller, model, or migration is needed.

**Tech Stack:** Laravel 13, PHP 8.3, `App\Models\Notification` (custom `notifications` table), SQLite in-memory for tests (RefreshDatabase), PHPUnit.

---

## File Map

| Action | File |
|--------|------|
| Modify | `app/Http/Controllers/ReceiveController.php` |
| Modify | `app/Http/Controllers/ReleaseController.php` |
| Modify | `app/Http/Controllers/TransactionApprovalController.php` |
| Create | `tests/Feature/TransactionNotificationTest.php` |

---

## Notification Matrix (exact spec)

| Type | Trigger location | Recipient | Title | Body |
|------|-----------------|-----------|-------|------|
| `tx_submitted` | ReceiveController/ReleaseController — staff path | Dept head; fallback: all admins | `"New [Receive/Release] Submission"` | `"[Name] submitted a [receive/release] request for [qty] [unit] of [item] — awaiting your approval."` |
| `tx_approved_receive` | TransactionApprovalController::approve() — received | Staff submitter | `"Receive Approved — Collect from Supply"` | `"Your receive request for [qty] [unit] of [item] was approved. Items have been added to inventory. Please collect from the Supply Department."` |
| `tx_approved_release` | TransactionApprovalController::approve() — released | Staff submitter | `"Release Approved"` | `"Your release request for [qty] [unit] of [item] was approved and inventory has been updated."` |
| `tx_rejected` | TransactionApprovalController::reject() | Staff submitter | `"Request Rejected"` | `"Your [receive/release] request for [item] was rejected. Reason: [head_rejection_notes]"` |

**All notifications link to:** `route('transactions.show', $transaction)`

**Admin fallback (tx_submitted only):** if no dept head exists, notify *all* admin users:
```php
$head = User::where('is_head', true)->where('department_id', $deptId)->first();
if ($head) {
    Notification::notify($head, 'tx_submitted', ...);
} else {
    User::where('role', 'admin')->each(fn ($admin) =>
        Notification::notify($admin->id, 'tx_submitted', ...)
    );
}
```

---

### Task 1: `tx_submitted` — notify head when staff submits a receive

**Files:**
- Test: `tests/Feature/TransactionNotificationTest.php` (create)
- Modify: `app/Http/Controllers/ReceiveController.php`

- [ ] **Step 1: Create the test file with a failing test**

Create `tests/Feature/TransactionNotificationTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionNotificationTest extends TestCase
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

    // ── tx_submitted ───────────────────────────────────────────────────────

    public function test_receive_submission_notifies_dept_head(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);

        $this->actingAs($staff)->post(route('receive.store'), [
            'name'          => 'New Widget',
            'qty'           => 5,
            'date_received' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $head->id,
            'type'    => 'tx_submitted',
        ]);
    }
}
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
php artisan test tests/Feature/TransactionNotificationTest.php::test_receive_submission_notifies_dept_head --stop-on-failure
```

Expected: FAIL — no `notifications` row.

- [ ] **Step 3: Add `tx_submitted` notification to `ReceiveController::store()` staff path**

Add imports at the top of `app/Http/Controllers/ReceiveController.php`:

```php
use App\Models\Notification;
use App\Models\User;
```

Replace the staff-path return block (starting from `Transaction::create([...])` through the return) with:

```php
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
                fn ($admin) => Notification::notify($admin->id, 'tx_submitted', 'New Receive Submission', $message, ['url' => $txUrl])
            );
        }

        return redirect()->route('receive.index')
            ->with('success', 'Submitted for head approval. Inventory will update once approved.');
```

Note: `Transaction::create()` is renamed to `$pendingTx = Transaction::create(...)` so we can pass it to `route('transactions.show', $pendingTx)`.

- [ ] **Step 4: Run the test to confirm it passes**

```bash
php artisan test tests/Feature/TransactionNotificationTest.php::test_receive_submission_notifies_dept_head --stop-on-failure
```

Expected: PASS.

- [ ] **Step 5: Run the full suite to confirm no regressions**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ReceiveController.php \
        tests/Feature/TransactionNotificationTest.php
git commit -m "$(cat <<'EOF'
feat: notify dept head when staff submits a receive (tx_submitted)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: `tx_submitted` — notify head when staff submits a release

**Files:**
- Modify: `tests/Feature/TransactionNotificationTest.php` (add test)
- Modify: `app/Http/Controllers/ReleaseController.php`

- [ ] **Step 1: Add the failing test**

Append inside the class in `tests/Feature/TransactionNotificationTest.php` (after the existing test):

```php
    public function test_release_submission_notifies_dept_head(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 10);

        $this->actingAs($staff)->post(route('release.store'), [
            'item_id'            => $item->id,
            'qty'                => 3,
            'released_to_office' => 'ICU',
            'receiver_name'      => 'Dr. Santos',
            'date_released'      => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $head->id,
            'type'    => 'tx_submitted',
        ]);
    }
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
php artisan test tests/Feature/TransactionNotificationTest.php::test_release_submission_notifies_dept_head --stop-on-failure
```

Expected: FAIL.

- [ ] **Step 3: Add `tx_submitted` notification to `ReleaseController::store()` staff path**

Add imports at the top of `app/Http/Controllers/ReleaseController.php`:

```php
use App\Models\Notification;
use App\Models\User;
```

Replace the staff-path Transaction::create() and return block with:

```php
        // Staff: do NOT decrement — leave for head to approve
        $pendingTx = Transaction::create([
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
            'department_id'         => $user->department_id,
            'head_approval_status'  => 'pending',
        ]);

        $txUrl   = route('transactions.show', $pendingTx);
        $message = "{$user->name} submitted a release request for {$request->qty} {$item->unit} of \"{$item->name}\" — awaiting your approval.";
        $deptId  = $user->department_id;
        $head    = User::where('is_head', true)->where('department_id', $deptId)->first();

        if ($head) {
            Notification::notify($head, 'tx_submitted', 'New Release Submission', $message, ['url' => $txUrl]);
        } else {
            User::where('role', 'admin')->each(
                fn ($admin) => Notification::notify($admin->id, 'tx_submitted', 'New Release Submission', $message, ['url' => $txUrl])
            );
        }

        return redirect()->route('release.index')
            ->with('success', 'Release submitted for head approval. Inventory will update once approved.');
```

Note: `auth()->user()->department_id` in the original Transaction::create() is replaced with `$user->department_id` (same value — `$user` is set earlier in the method on line 43).

- [ ] **Step 4: Run the test to confirm it passes**

```bash
php artisan test tests/Feature/TransactionNotificationTest.php::test_release_submission_notifies_dept_head --stop-on-failure
```

Expected: PASS.

- [ ] **Step 5: Run the full suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ReleaseController.php \
        tests/Feature/TransactionNotificationTest.php
git commit -m "$(cat <<'EOF'
feat: notify dept head when staff submits a release (tx_submitted)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: `tx_approved_receive` + `tx_approved_release` — notify submitter on approval

**Files:**
- Modify: `tests/Feature/TransactionNotificationTest.php` (add 2 tests)
- Modify: `app/Http/Controllers/TransactionApprovalController.php`

- [ ] **Step 1: Add the failing tests**

Append inside the class in `tests/Feature/TransactionNotificationTest.php`:

```php
    // ── tx_approved ────────────────────────────────────────────────────────

    public function test_approving_receive_notifies_submitter(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);

        $tx = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'pcs',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $this->actingAs($head)->patch(route('approvals.approve', $tx));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type'    => 'tx_approved_receive',
        ]);
    }

    public function test_approving_release_notifies_submitter(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 10);

        $tx = Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 3,
            'unit'                  => 'pcs',
            'released_to_office'    => 'ICU',
            'receiver_name'         => 'Dr. Santos',
            'released_by_user_id'   => $staff->id,
            'department_id'         => $dept->id,
            'head_approval_status'  => 'pending',
        ]);

        $this->actingAs($head)->patch(route('approvals.approve', $tx));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type'    => 'tx_approved_release',
        ]);
    }
```

- [ ] **Step 2: Run both tests to confirm they fail**

```bash
php artisan test tests/Feature/TransactionNotificationTest.php::test_approving_receive_notifies_submitter tests/Feature/TransactionNotificationTest.php::test_approving_release_notifies_submitter --stop-on-failure
```

Expected: FAIL.

- [ ] **Step 3: Add notification block to `TransactionApprovalController::approve()`**

Add import at the top of `app/Http/Controllers/TransactionApprovalController.php`:

```php
use App\Models\Notification;
```

After `$transaction->update($updates);` in the `approve()` method, insert:

```php
        $transaction->update($updates);

        $submitterId = $transaction->type === 'received'
            ? $transaction->received_by_user_id
            : $transaction->released_by_user_id;

        if ($submitterId) {
            if ($transaction->type === 'received') {
                Notification::notify(
                    $submitterId,
                    'tx_approved_receive',
                    'Receive Approved — Collect from Supply',
                    "Your receive request for {$transaction->qty} {$transaction->unit} of \"{$transaction->item_name_snapshot}\" was approved. Items have been added to inventory. Please collect from the Supply Department.",
                    ['url' => route('transactions.show', $transaction)]
                );
            } else {
                Notification::notify(
                    $submitterId,
                    'tx_approved_release',
                    'Release Approved',
                    "Your release request for {$transaction->qty} {$transaction->unit} of \"{$transaction->item_name_snapshot}\" was approved and inventory has been updated.",
                    ['url' => route('transactions.show', $transaction)]
                );
            }
        }
```

- [ ] **Step 4: Run the tests to confirm they pass**

```bash
php artisan test tests/Feature/TransactionNotificationTest.php::test_approving_receive_notifies_submitter tests/Feature/TransactionNotificationTest.php::test_approving_release_notifies_submitter --stop-on-failure
```

Expected: PASS.

- [ ] **Step 5: Run the full suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TransactionApprovalController.php \
        tests/Feature/TransactionNotificationTest.php
git commit -m "$(cat <<'EOF'
feat: notify submitter when receive or release is approved (tx_approved)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: `tx_rejected` — notify submitter on rejection

**Files:**
- Modify: `tests/Feature/TransactionNotificationTest.php` (add test)
- Modify: `app/Http/Controllers/TransactionApprovalController.php`

- [ ] **Step 1: Add the failing test**

Append inside the class in `tests/Feature/TransactionNotificationTest.php`:

```php
    // ── tx_rejected ────────────────────────────────────────────────────────

    public function test_rejecting_transaction_notifies_submitter(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $head  = $this->makeStaff($dept, isHead: true);
        $item  = $this->makeItem($dept, 0);

        $tx = Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 5,
            'unit'                 => 'pcs',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $staff->id,
            'department_id'        => $dept->id,
            'head_approval_status' => 'pending',
        ]);

        $this->actingAs($head)->patch(route('approvals.reject', $tx), [
            'notes' => 'Wrong quantity, please correct.',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $staff->id,
            'type'    => 'tx_rejected',
        ]);
    }
```

- [ ] **Step 2: Run the test to confirm it fails**

```bash
php artisan test tests/Feature/TransactionNotificationTest.php::test_rejecting_transaction_notifies_submitter --stop-on-failure
```

Expected: FAIL.

- [ ] **Step 3: Add notification to `TransactionApprovalController::reject()`**

After `$transaction->update([...])` in the `reject()` method, insert:

```php
        $transaction->update([
            'head_approval_status' => 'rejected',
            'head_rejection_notes' => $request->notes,
        ]);

        $submitterId = $transaction->type === 'received'
            ? $transaction->received_by_user_id
            : $transaction->released_by_user_id;

        if ($submitterId) {
            $txKind = $transaction->type === 'received' ? 'receive' : 'release';
            Notification::notify(
                $submitterId,
                'tx_rejected',
                'Request Rejected',
                "Your {$txKind} request for \"{$transaction->item_name_snapshot}\" was rejected. Reason: {$request->notes}",
                ['url' => route('transactions.show', $transaction)]
            );
        }
```

(The `use App\Models\Notification;` import was added in Task 3.)

- [ ] **Step 4: Run the test to confirm it passes**

```bash
php artisan test tests/Feature/TransactionNotificationTest.php::test_rejecting_transaction_notifies_submitter --stop-on-failure
```

Expected: PASS.

- [ ] **Step 5: Run the full suite**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/TransactionApprovalController.php \
        tests/Feature/TransactionNotificationTest.php
git commit -m "$(cat <<'EOF'
feat: notify submitter when transaction is rejected with reason (tx_rejected)

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Edge-case tests — admin fallback and no notification on auto-approve

**Files:**
- Modify: `tests/Feature/TransactionNotificationTest.php` (add 2 tests)

These tests verify existing correct behaviour — no implementation changes needed.

- [ ] **Step 1: Add the edge-case tests**

Append inside the class in `tests/Feature/TransactionNotificationTest.php`:

```php
    // ── Edge cases ─────────────────────────────────────────────────────────

    public function test_receive_submission_notifies_all_admins_when_no_head_in_dept(): void
    {
        $dept   = $this->makeDept();
        $staff  = $this->makeStaff($dept);          // no head in this dept
        $admin1 = User::factory()->create(['role' => 'admin', 'department_id' => null]);
        $admin2 = User::factory()->create(['role' => 'admin', 'department_id' => null]);

        $this->actingAs($staff)->post(route('receive.store'), [
            'name'          => 'Fallback Widget',
            'qty'           => 2,
            'date_received' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('notifications', ['user_id' => $admin1->id, 'type' => 'tx_submitted']);
        $this->assertDatabaseHas('notifications', ['user_id' => $admin2->id, 'type' => 'tx_submitted']);
    }

    public function test_head_self_submission_does_not_create_tx_submitted_notification(): void
    {
        $dept = $this->makeDept();
        $head = $this->makeStaff($dept, isHead: true);

        $this->actingAs($head)->post(route('receive.store'), [
            'name'          => 'Head Widget',
            'qty'           => 5,
            'date_received' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseMissing('notifications', [
            'type' => 'tx_submitted',
        ]);
    }
```

- [ ] **Step 2: Run both tests**

```bash
php artisan test tests/Feature/TransactionNotificationTest.php::test_receive_submission_notifies_all_admins_when_no_head_in_dept tests/Feature/TransactionNotificationTest.php::test_head_self_submission_does_not_create_tx_submitted_notification
```

Expected: PASS (no implementation changes needed — behaviours are already correct from Tasks 1–4).

- [ ] **Step 3: Run the full suite one final time**

```bash
php artisan test
```

Expected: all tests pass.

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/TransactionNotificationTest.php
git commit -m "$(cat <<'EOF'
test: edge cases — all-admin fallback and no notification on auto-approve

Co-Authored-By: Claude Sonnet 4.6 <noreply@anthropic.com>
EOF
)"
```

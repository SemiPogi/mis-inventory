# Head Approval for Receive & Release — Design Spec

**Date:** 2026-05-31
**Status:** Approved

---

## Goal

Add a department head (or admin) approval step to the Receive and Release workflows. Item quantities are only updated after approval. Every submission — including those made by the dept head themselves — requires approval.

---

## Architecture

Option 1: add 4 approval-tracking columns to the existing `transactions` table. Same pattern already used by `ris_requests`. No new tables.

---

## Section 1 — Database

**Migration:** add to `transactions`:

| Column | Type | Default | Notes |
|---|---|---|---|
| `head_approval_status` | enum `pending\|approved\|rejected` | nullable | `null` = pre-existing record, treated as approved |
| `head_approved_by_id` | FK → users | nullable | who approved or rejected |
| `head_approved_at` | timestamp | nullable | when the decision was made |
| `head_rejection_notes` | text | nullable | required when rejecting |

`Transaction` model: add columns to `$fillable`, add `headApprovedBy()` belongs-to relation, add helpers `isPendingApproval()`, `isApproved()`, `isRejected()`.

---

## Section 2 — Workflow Logic

### Receive

```
Staff submits receive form
  → Transaction created: type=received, head_approval_status=pending
  → Item qty NOT added to inventory yet

Head / Admin approves
  → Item added to inventory (create or increment)
  → head_approval_status = approved, head_approved_by_id, head_approved_at set

Head / Admin rejects
  → head_approval_status = rejected, head_rejection_notes saved
  → No inventory change
```

### Release

```
Staff submits release form
  → Transaction created: type=released, head_approval_status=pending
  → Item qty NOT decremented yet

Head / Admin approves
  → Item qty decremented
  → head_approval_status = approved
  → acknowledgment_status = pending (ack flow begins)

Head / Admin rejects
  → head_approval_status = rejected, head_rejection_notes saved
  → Item qty unchanged
```

### Authorization rules

| Who | Can approve |
|---|---|
| Dept Head | Transactions from their department, **except their own submissions** |
| Admin | Any department's transactions, **except their own submissions** |
| Staff | Cannot approve |

Self-approval is always blocked (403) — if `$transaction->created_by === auth()->id()`, reject regardless of role.

---

## Section 3 — New Controller: `TransactionApprovalController`

**Routes:**

```
GET    /approvals                          → index   (approvals.index)
PATCH  /approvals/{transaction}/approve   → approve (approvals.approve)
PATCH  /approvals/{transaction}/reject    → reject  (approvals.reject)
```

**Middleware:** `auth` + `role:admin,staff` (dept heads are `staff` role with `is_head = true`). Authorization enforced inside controller.

**`index()`**
- Query `transactions` where `head_approval_status = pending`
- Dept Head: scoped to `department_id = user's dept`
- Admin: all departments
- Split into two collections: `$pendingReceives` (type=received) and `$pendingReleases` (type=released)

**`approve(Transaction $tx)`**
- Block self-approval
- Verify user can approve this dept's transactions
- If `type = received`: find or create Item, increment qty
- If `type = released`: decrement item qty, set `acknowledgment_status = pending`
- Update: `head_approval_status = approved`, `head_approved_by_id`, `head_approved_at`

**`reject(Request $request, Transaction $tx)`**
- Validate `notes` required
- Block self-approval
- Verify authorization
- Update: `head_approval_status = rejected`, `head_rejection_notes = $request->notes`
- No inventory change

---

## Section 4 — Changes to Existing Controllers

### `ReceiveController::store()`
- Remove all inventory update logic (finding/creating Item, logging Transaction)
- Create transaction with `head_approval_status = pending`
- Do NOT update item qty
- Redirect to receive index with "Submitted for head approval" message

### `ReleaseController::store()`
- Remove qty decrement
- Create transaction with `head_approval_status = pending`, `acknowledgment_status = pending`
- Do NOT decrement item qty
- Redirect to release index with "Submitted for head approval" message

---

## Section 5 — New View: `resources/views/approvals/index.blade.php`

Two sections on one page:

**Pending Receives** table columns: Item | Qty | Unit | Received From | Submitted By | Date | Actions (Approve / Reject)

**Pending Releases** table columns: Item | Qty | Unit | Released To | Office | Submitted By | Date | Actions (Approve / Reject)

Admin gets an extra **Dept** column in both tables.

Reject action: inline Alpine.js expand (same pattern as RIS head reject) — shows textarea for notes, then Confirm Reject button.

---

## Section 6 — Sidebar Badge

In `layouts/app.blade.php`:

```php
$approvalBadge = 0;
if ($user->is_head || $user->isAdmin()) {
    $approvalBadge = Transaction::where('head_approval_status', 'pending')
        ->when(!$user->isAdmin(), fn($q) => $q->where('department_id', $user->department_id))
        ->count();
}
```

Note: the badge shows all pending for the dept. Self-approval is only blocked at the approve/reject action level inside the controller.

New sidebar nav item **"Approvals"** with badge, shown only to heads and admins.

---

## Section 7 — Affected Existing Pages

### `AcknowledgeController::index()` — pending list
Add filter so only head-approved (or legacy null) releases appear:
```php
->where(fn($q) => $q->whereNull('head_approval_status')
                    ->orWhere('head_approval_status', 'approved'))
```

### `AcknowledgeController::index()` — history
Same filter applied to acknowledged history query.

### Receive & Release index pages
Show `head_approval_status` as a status badge on each row: **Pending**, **Approved**, **Rejected** (with rejection notes on hover/expand).

---

## Out of Scope

- Email/push notifications on approval/rejection (can be added later)
- Approval for RIS-created transactions (those already have their own flow)
- Approval for IAR (supply-only, different flow)

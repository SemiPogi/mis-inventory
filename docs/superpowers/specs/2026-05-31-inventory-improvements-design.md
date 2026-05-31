# Inventory Improvements — Design Spec

**Date:** 2026-05-31
**Status:** Approved

---

## Goal

Add 6 feature groups to the mis-inventory system, plus a frontend UX reorganization that keeps the UI clean as features grow. Each group is independently shippable.

---

## Execution Order

| Group | Name | Size |
|---|---|---|
| A | Pending Self-Service (Cancel + Re-submit) | Small |
| B | Notifications (submit → head, approve/reject/collect → staff) | Small-Medium |
| C | Approvals UX (Bulk Approve + Stock Reservation) | Medium |
| D | Print Slip (Receive / Release) | Medium |
| E | Monitoring (Low Stock Alerts + Audit Log) | Medium-Large |
| F | Warranty Tracking | Medium |
| UX | Frontend Reorganization (Dashboard + Sidebar + Item tabs) | Medium |

UX reorganization should be done **alongside Group E/F** since those groups add the most content.

---

## Group A — Pending Self-Service

### A1. Cancel Pending Transaction

**Who:** Staff who submitted the transaction.
**When:** Only when `head_approval_status = 'pending'`.

**Migration:** Add `'cancelled'` to the `head_approval_status` enum on `transactions`.

**Route:** `PATCH /transactions/{transaction}/cancel` → `TransactionCancelController@cancel`

**Authorization:** Only the original submitter can cancel:
- Receive: `received_by_user_id === auth()->id()`
- Release: `released_by_user_id === auth()->id()`

**Controller logic:**
1. Verify transaction is `pending` and belongs to the auth user
2. Set `head_approval_status = 'cancelled'`
3. **Item cleanup (receive only):** if `item.current_qty === 0` AND no other non-cancelled transactions reference `item_id` → delete the item record
4. Redirect back with flash: `"Your submission has been cancelled."`

**Head is NOT notified** — routine self-correction, no noise needed.

**UI:** Cancel button shown on:
- `transactions.blade.php` — inline on rows where user is submitter + status is pending
- `transactions-show.blade.php` — action button in header area

---

### A2. Re-submit After Rejection

**Who:** Staff who submitted the (now rejected) transaction.
**When:** Only when `head_approval_status = 'rejected'`.

**Route:** `GET /transactions/{transaction}/resubmit` → redirects to receive or release form with query params pre-filling field values

**Logic:**
- If `type = received`: redirect to `/receive?name=...&qty=...&received_from=...&ris_iar_number=...&unit=...&remarks=...`
- If `type = released`: redirect to `/release?item_id=...&qty=...&released_to_office=...&receiver_name=...&receiver_designation=...&purpose=...&date_released=...&remarks=...`
- Submitting creates a **new** transaction — the rejected one stays as history
- Receive/Release controllers already read `old()` for repopulation; query params fall through to `old()` via a middleware shim or the form reads `request()->query()` as fallback

**UI:** Re-submit button shown on:
- `transactions.blade.php` — inline on rows where user is submitter + status is rejected
- `transactions-show.blade.php` — action button

Show the rejection reason alongside the Re-submit button:
```
Rejected: "Insufficient documentation provided."  [Re-submit →]
```

---

## Group B — Notifications

Uses the existing `Notification::notify($userId, $type, $title, $body, $data)` helper.

### New notification types

| Type constant | Trigger | Recipient | Title | Body |
|---|---|---|---|---|
| `tx_submitted` | Staff submits receive or release | Dept head of that dept | `"New [Receive/Release] Submission"` | `"[Name] submitted a [receive/release] request for [qty] [unit] of [item] — awaiting your approval."` |
| `tx_approved_receive` | Head/admin approves a receive | Staff submitter | `"Receive Approved — Collect from Supply"` | `"Your receive request for [qty] [unit] of [item] was approved. Items have been added to inventory. Please collect from the Supply Department."` |
| `tx_approved_release` | Head/admin approves a release | Staff submitter | `"Release Approved"` | `"Your release request for [qty] [unit] of [item] was approved and inventory has been updated."` |
| `tx_rejected` | Head/admin rejects | Staff submitter | `"Request Rejected"` | `"Your [receive/release] request for [item] was rejected. Reason: [head_rejection_notes]"` |

### Dept head lookup

```php
$head = User::where('is_head', true)
    ->where('department_id', $transaction->department_id)
    ->first();

// Fallback: notify all admins if no head assigned
if (! $head) {
    User::where('role', 'admin')->each(fn($admin) =>
        Notification::notify($admin->id, ...)
    );
}
```

### Where to trigger

| Location | Trigger |
|---|---|
| `ReceiveController::store()` — staff path | `tx_submitted` → head |
| `ReleaseController::store()` — staff path | `tx_submitted` → head |
| `TransactionApprovalController::approve()` — received | `tx_approved_receive` → submitter |
| `TransactionApprovalController::approve()` — released | `tx_approved_release` → submitter |
| `TransactionApprovalController::reject()` | `tx_rejected` → submitter |
| `TransactionApprovalController::bulkApprove()` *(Group C)* | same as single approve, per transaction |

### Notification URL

All notifications link to `route('transactions.show', $transaction)` so the user can see the full context.

---

## Group C — Approvals UX

### C1. Bulk Approve

**Route:** `POST /approvals/bulk-approve` → `TransactionApprovalController@bulkApprove`

**Request:** `ids[]` — array of transaction IDs (validated: must exist, must be pending, must be in approver's scope)

**Logic:**
```
foreach ids as id:
    load transaction
    verify approver authorization (same as single approve)
    if passes: run approve logic (qty update + head_approval_status = approved + notifications)
    else: add to $failed list with reason
```

On partial failure: approve the passing ones, redirect back with:
- Success flash listing how many approved
- Warning flash listing failed items and reasons (e.g. "Bond Paper: insufficient stock")

**UI changes to `approvals/index.blade.php`:**
- Checkbox column added as first column in both tables
- "Select All" checkbox in thead
- Sticky action bar appears at bottom of screen when ≥1 checkbox is checked (Alpine.js reactive):
  ```
  [  ] 3 selected    [Approve Selected (3)]
  ```
- Action bar uses `position: sticky; bottom: 0` with a white/surface background

### C2. Stock Reservation Display

**Where:** Release form item dropdown + release validation.

**How it works:**
- `ReleaseController::index()` queries: sum of `qty` for pending releases per item
  ```php
  $reservations = Transaction::where('type', 'released')
      ->where('head_approval_status', 'pending')
      ->when($scope, fn($q) => $q->where('department_id', $scope))
      ->selectRaw('item_id, SUM(qty) as reserved_qty')
      ->groupBy('item_id')
      ->pluck('reserved_qty', 'item_id');
  ```
- Passed to view as `$reservations` (item_id → reserved_qty map)
- Item option text: `Bond Paper A4 — (250 pcs available, 10 reserved)`
- `data-reserved` attribute added to each option for Alpine.js
- `availableLabel` computed field in `releaseForm()` shows: `250 available / 10 pending approval`
- Soft warning shown (not hard block) if `qty > (current_qty - reserved_qty)`:
  > "Note: 10 pcs are pending approval. Releasing may exceed available stock if those are approved first."

---

## Group D — Print Slip

**Controller:** `TransactionPrintController` (new, single method)

**Route:** `GET /transactions/{transaction}/print` → `TransactionPrintController@show` (name: `transactions.print`)

**Authorization:** Same dept scope as `TransactionController` — staff can print own dept, admin prints any.

**View:** `resources/views/transactions/print.blade.php`

Same letterhead pattern as `ris/print.blade.php`:
- Full-width `Header.svg` at top
- Full-width `Footer.svg` at bottom
- `@page { size: letter portrait; margin: 0; }` flex column layout
- Screen-only print/back buttons

**Receive slip layout:**

```
                    PROPERTY AND SUPPLY SECTION
                    ITEM RECEIPT SLIP

Division: [dept name]                    Date: [date_received]
                                         Ref No.: [ris_iar_number]

ITEM                QTY    UNIT    RECEIVED FROM
[item_name]         [qty]  [unit]  [received_from]

Remarks: [remarks]

Submitted by:              Approved by:
________________           ________________
[receiver name]            [head name or blank]
[date]                     [approved date or blank]
```

**Release slip layout:**

```
                    PROPERTY AND SUPPLY SECTION
                    ITEM RELEASE SLIP

Division: [dept name]                    Date: [date_released]

ITEM         QTY    UNIT    RELEASED TO          OFFICE
[item_name]  [qty]  [unit]  [receiver_name]      [released_to_office]

Purpose: [purpose]
Remarks: [remarks]

Released by:               Approved by:
________________           ________________
[released_by name]         [head name or blank]
[date]                     [approved date or blank]
```

**Status line at bottom:** `Status: [head_approval_status] | Printed: [now]`

**UI:** Print button added to `transactions-show.blade.php`.

---

## Group E — Monitoring

### E1. Low Stock Alerts — All Departments

**Current state:** `DashboardController` has a low stock query but it's scoped to supply hub only.

**Change:** Remove supply-hub restriction. Use the normal dept scope (`auth()->user()->departmentScope()`). Items show in low stock alert when `current_qty < min_stock_qty AND min_stock_qty > 0`.

**Dashboard:** Low stock items added to the **Unified Alerts Card** (see UX section) — no new separate card.

**Item show page:** Amber badge `"Low Stock"` shown next to qty if below min. Red `"Out of Stock"` if qty = 0.

### E2. Audit Log

**New table: `item_logs`**

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `item_id` | FK → items | |
| `user_id` | FK → users | who caused the change |
| `action` | enum | `received / released / approved_receive / approved_release / rejected / cancelled` |
| `qty_change` | integer | positive = added, negative = deducted |
| `qty_before` | integer | snapshot |
| `qty_after` | integer | snapshot |
| `note` | string, nullable | e.g. transaction ID or rejection reason |
| `created_at` | timestamp | |

No `updated_at` — logs are immutable.

**Model:** `ItemLog` — belongs to `Item`, belongs to `User`. `$fillable` all columns. No `updated_at` (use `public $timestamps = false; public static function boot() { ... }` or single timestamp via `const CREATED_AT`).

**Where to log:**

| Location | Action logged |
|---|---|
| `ReceiveController::store()` — auto-approved path | `approved_receive`, qty_change = +qty |
| `ReleaseController::store()` — auto-approved path | `approved_release`, qty_change = -qty |
| `TransactionApprovalController::approve()` — receive | `approved_receive`, qty_change = +qty |
| `TransactionApprovalController::approve()` — release | `approved_release`, qty_change = -qty |
| `TransactionCancelController::cancel()` *(Group A)* | `cancelled`, qty_change = 0 |

**Display:** Audit log tab on item show page — chronological timeline:

```
✅ approved_receive   +5 pcs    250 → 255   Jun 1, 2026  Maria Santos
📤 approved_release  -2 pcs    255 → 253   Jun 1, 2026  Juan dela Cruz
❌ rejected           0 pcs    253 → 253   Jun 2, 2026  Dept Head
```

---

## Group F — Warranty Tracking

### Migration

Add 4 columns to `items`:

| Column | Type | Notes |
|---|---|---|
| `warranty_expiry_date` | date, nullable | |
| `warranty_provider` | string(255), nullable | e.g. "Samsung Philippines" |
| `warranty_reference_no` | string(100), nullable | warranty claim/serial reference |
| `warranty_notes` | text, nullable | coverage details, exclusions |

Add to `Item::$fillable`.

### Warranty status helper on Item model

```php
public function warrantyStatus(): ?string
{
    if (! $this->warranty_expiry_date) return null;
    $days = now()->diffInDays($this->warranty_expiry_date, false);
    if ($days < 0)  return 'expired';
    if ($days <= 30) return 'expiring';     // red
    if ($days <= 90) return 'expiring-soon'; // amber
    return 'active';                         // green
}
```

### Where it appears

**Receive form:** New collapsible "Warranty Information" section at bottom of form, collapsed by default. Alpine `x-show` toggle. Fields: provider, reference no., expiry date, notes.

**Item show page (Overview tab):** Warranty card shown only if any warranty field is populated:
```
┌─────────────────────────────┐
│ 🛡 Warranty                 │
│ Provider: Samsung PH        │
│ Reference: WR-2024-001234   │
│ Expires: Dec 31, 2027       │  🟢 Active
│ Coverage: Parts and labor   │
└─────────────────────────────┘
```

**Item edit page:** Same 4 fields editable.

**Dashboard Alerts card (Warranty tab):** Items with warranty expiring within 90 days, sorted by date. Red rows ≤30 days, amber rows ≤90 days.

---

## UX Reorganization

### Dashboard — Unified Alerts Card

Replace separate Expiry Alerts card (and future Low Stock / Warranty cards) with a single **"⚠ Alerts"** card using Alpine.js tabs.

```blade
<div x-data="{ tab: 'expiry' }">
  <div class="tabs"> <!-- Expiry (N) | Warranty (N) | Low Stock (N) --> </div>
  <div x-show="tab === 'expiry'">  <!-- expiry table --> </div>
  <div x-show="tab === 'warranty'">  <!-- warranty table --> </div>
  <div x-show="tab === 'low-stock'">  <!-- low stock table --> </div>
</div>
```

Tab badges show count. Tab is hidden if its count = 0 (only shown tabs that have items). Entire card hidden if all counts = 0.

### Sidebar — Grouped Collapsible Sections

Replace flat nav list with labeled section groups. State stored in `localStorage` per section key.

**Section structure:**

```
Dashboard                          (no section header, always visible)

▼ INVENTORY
    Receive / Release / Acknowledge / Transactions / Items

▼ APPROVALS  [combined badge]
    Approvals [N] / RIS Approvals [N] / Transfer Approvals [N]

▼ REQUISITIONS
    My RIS / Supply Queue

▼ OPERATIONS
    Transfers / Assemblies / IAR Records

▼ FINANCE
    Petty Cash [N]

▼ ADMIN  (admin only)
    Reports / Users / Departments / Item Categories
```

**APPROVALS section badge:** sum of `$approvalBadge + $risHeadBadge + $transferHeadBadge` — visible even when section is collapsed.

**Implementation:** Each section uses Alpine `x-data="{ open: localStorage.getItem('nav-[section]') !== '0' }"` with toggle saved to localStorage.

**Collapsed sidebar (icon-only mode):** Section headers show as a divider line only. Nav items still show their icons with tooltips.

### Item Show Page — Tabbed Layout

Replace single long page with 3 tabs. Tab selection stored in URL hash so browser back/forward works.

```
Bond Paper A4  —  253 pcs available
[Overview]  [History]  [Audit Log]
────────────────────────────────────
```

**Overview tab:** item meta (name, category, brand, model, serial, unit, min stock, expiry, created by) + warranty card (if any warranty data) + low stock / out-of-stock badge

**History tab:** existing transactions table (already on this page, just moved to a tab)

**Audit Log tab:** timeline from `item_logs` (Group E)

**Controller:** `ItemController::show()` already loads what's needed. Add `$logs = $item->logs()->with('user')->latest()->get();` when audit log is implemented. Pass as `$logs` to view.

---

## Out of Scope

- Email or SMS notifications (in-app only for now)
- Warranty claims management (tracking claims submitted to providers)
- Stock adjustment / manual correction tool (separate feature)
- Mobile app / barcode scanning

---

## Spec Self-Review

### Placeholder scan
No TBD or TODO sections. All features have defined routes, models, and UI placement. ✓

### Internal consistency
- `cancelled` enum value in Group A is used consistently across cancel controller, item cleanup logic, audit log action values, and badge exclusion queries.
- Notification helper signature matches existing `Notification::notify($userId, $type, $title, $body, $data)` pattern used by RIS.
- Bulk approve in Group C uses the same per-transaction logic as single approve — no duplication of business logic.
- Unified Alerts card in UX section references both Group E (low stock) and Group F (warranty) data — both must be passed from `DashboardController`.

### Scope check
6 feature groups + 1 UX group = 7 independent implementation plans. Each plan produces working, testable software. ✓

### Ambiguity check
- "Delete item if no other non-cancelled transactions" — Group A cancel logic. Explicit: check `Transaction::where('item_id', $item->id)->whereNotIn('head_approval_status', ['cancelled'])->exists()`.
- Warranty alerts: amber = ≤90 days, red = ≤30 days. Explicit in `warrantyStatus()` helper. ✓
- Bulk approve partial failure: approve passing ones, report failures. Does not roll back successes. ✓

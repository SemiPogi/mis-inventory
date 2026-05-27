# Design Spec: Petty Cash Tracking, User Roles & Reports
**Date:** 2026-05-27
**Project:** MIS Inventory — La Union Medical Center
**Scope:** MIS department only (hospital-wide expansion is a future sprint)

---

## 1. Overview

Add petty cash voucher tracking to the MIS Inventory system. Each voucher records a single store purchase funded by a cash advance from accounting. Purchased items are automatically added to the inventory as received transactions. A three-role user system (Admin, Staff, Accounting) controls access. A unified Reports hub covers both inventory movement and petty cash auditing.

---

## 2. User Roles

Three roles are added to the `users` table via a `role` enum column.

| Role | Description |
|---|---|
| **admin** | Full access. User management, delete/edit any record, all reports, all petty cash actions. |
| **staff** | Day-to-day operations. Create vouchers, receive/release items, acknowledge their own vouchers. |
| **accounting** | Cash management. View all vouchers, mark change returned (settle), access all reports. Cannot create vouchers. |

### Access Matrix

| Feature | Staff | Accounting | Admin |
|---|---|---|---|
| View inventory / transactions | ✅ | ✅ | ✅ |
| Receive / Release items | ✅ | ✅ | ✅ |
| Acknowledge item releases | ✅ | ✅ | ✅ |
| Create petty cash voucher | ✅ | ❌ | ✅ |
| Acknowledge any voucher (not just own) | ✅ | ❌ | ✅ |
| Mark change returned (settle) | ❌ | ✅ | ✅ |
| View audit reports | ❌ | ✅ | ✅ |
| Manage users | ❌ | ❌ | ✅ |
| Delete / void any record | ❌ | ❌ | ✅ |

### Migration
- Add `role` enum(`admin`, `staff`, `accounting`) to `users` table, default `staff`
- Add `is_active` boolean to `users` table, default `true` (for deactivation support)
- Existing users are assigned `admin` role on migration — no lockouts

### Middleware
A single `EnsureRole` middleware accepts a list of allowed roles and is applied per route group.

---

## 3. Petty Cash Module

### 3.1 Data Model

#### `petty_cash_vouchers`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `voucher_number` | string | Auto-generated: `PCV-YYYY-NNN` (e.g. PCV-2026-001), unique |
| `or_number` | string | Official Receipt number |
| `store_name` | string | Where items were purchased |
| `releasing_officer` | string | Accounting person who disbursed the cash |
| `requested_amount` | decimal(8,2) | Amount drawn from accounting (max ₱2,000) |
| `transport_fee` | decimal(8,2) | Default 0. Nullable transport cost. |
| `total_amount` | decimal(8,2) | Sum of all line item totals + transport_fee |
| `change_amount` | decimal(8,2) | `requested_amount − total_amount` (must be ≥ 0) |
| `date_purchased` | date | Date of purchase |
| `status` | enum | `draft`, `submitted`, `acknowledged`, `settled` |
| `acknowledged_by` | FK → users | Nullable. Staff user who acknowledged. |
| `acknowledged_at` | timestamp | Nullable. |
| `change_returned_by` | FK → users | Nullable. Accounting user who confirmed return. |
| `change_returned_at` | timestamp | Nullable. |
| `created_by` | FK → users | Staff who created the voucher. |
| `remarks` | text | Nullable. |
| `timestamps` | | |

#### `petty_cash_items`

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `petty_cash_voucher_id` | FK | |
| `item_id` | FK → items | Nullable. Set after inventory match/create on submit. |
| `item_name` | string | Name as typed (snapshot). |
| `qty` | decimal(8,2) | Quantity purchased. |
| `unit` | string | pcs, reams, boxes, etc. |
| `unit_cost` | decimal(8,2) | Cost per unit. |
| `total_cost` | decimal(8,2) | Stored: `qty × unit_cost`. |
| `timestamps` | | |

### 3.2 Voucher Lifecycle

```
Draft → Submitted → Acknowledged → Settled
```

| Status | Actor | Trigger |
|---|---|---|
| **Draft** | Staff | Voucher form is being filled. Not yet committed to inventory. |
| **Submitted** | Staff | Form submitted. Line items auto-create inventory received transactions. Awaiting staff acknowledgement. |
| **Acknowledged** | Staff | Staff reviews and confirms purchase details and change amount are correct. |
| **Settled** | Accounting | Accounting confirms cash change has been physically returned. Records who settled and when. |

Admin can force any status transition and can void/delete vouchers.

### 3.3 Auto-Inventory Integration

On voucher submission (status → `submitted`), for each `petty_cash_item`:
1. Search `items` table for an existing item by name (case-insensitive trim match).
2. **If found:** Increment `current_qty` and `total_qty_received` by `qty`. Create a `Transaction` record: `type = received`, `item_id`, `item_name_snapshot`, `qty`, `unit`, `received_from = store_name`, `ris_iar_number = or_number`, `date_received = date_purchased`.
3. **If not found:** Create a new `Item` (name, unit, `current_qty = qty`, `total_qty_received = qty`, `created_by`). Then create the `Transaction` as above.
4. Set `petty_cash_items.item_id` to the matched or newly created item.

This logic runs inside a database transaction — if any step fails, the entire voucher submission is rolled back.

### 3.4 Financial Validation
- `requested_amount` max: ₱2,000.00
- `change_amount = requested_amount − total_amount` must be ≥ 0 (cannot overspend the request)
- `total_amount` is recomputed server-side on submit (never trusted from the form)

### 3.5 Voucher Number Generation
Format: `PCV-YYYY-NNN` where NNN is a zero-padded sequence that resets each year. Generated inside a database lock to prevent duplicates under concurrent requests.

---

## 4. Dashboard Integration

The existing dashboard bento grid gains new stat tiles and a petty cash activity section.

### New Stat Tiles
| Tile | Visible to | Value |
|---|---|---|
| Petty Cash This Month | All | Total `total_amount` of submitted+acknowledged+settled vouchers in current calendar month |
| Vouchers This Month | All | Count of vouchers this month (with settled vs pending breakdown as subtext) |
| Pending Acknowledgement | Staff, Admin | Count of `submitted` vouchers created by or assigned to the user |
| Pending Settlement | Accounting, Admin | Count of `acknowledged` vouchers awaiting change return |

### Recent Petty Cash Activity
A small table below the stat tiles showing the last 5 vouchers: voucher number, store, amount, change due, status badge.

### Sidebar Notification Badges
- **Staff:** Red dot on Petty Cash nav link when `submitted` vouchers await their acknowledgement.
- **Accounting:** Red dot on Petty Cash nav link when `acknowledged` vouchers await settlement.

---

## 5. Reports Hub

Accessible to **Accounting** and **Admin** only. Route: `/reports`. Single page with two tab groups: **Inventory** and **Petty Cash**.

### 5.1 Inventory Reports

| Report | Filters | Export |
|---|---|---|
| **Received Items** | Date range, item name, supplier (received_from) | CSV, PDF |
| **Released Items** | Date range, item name, office (released_to_office) | CSV, PDF |
| **Stock Movement** | Item (required), date range | CSV, PDF |
| **Current Stock Snapshot** | — | CSV, PDF |
| **Acknowledgement Status** | Status (pending/done), date range | CSV |

### 5.2 Petty Cash Reports

| Report | Filters | Export |
|---|---|---|
| **Voucher Ledger** | Date range, status, releasing officer | CSV, PDF |
| **Monthly Summary** | Year | CSV, PDF |
| **Outstanding Changes** | — (always live) | CSV |
| **Item Purchase History** | Date range, item name | CSV |
| **Per-Voucher Print View** | Single voucher | PDF / browser print |

### Per-Voucher Print View
A printable single-page layout including: voucher header (number, date, store, OR#, officer), line items table with unit costs and totals, transport fee row, grand total row, change due row, and a signatures section with three signature lines: **Prepared by** (Staff), **Acknowledged by** (Staff), **Released by** (Accounting).

---

## 6. User Management (Admin only)

Route: `/users`

| Action | Description |
|---|---|
| List users | Table of all users: name, email, role, created date |
| Create user | Name, email, password, role assignment |
| Edit user | Change name, email, role. Reset password. |
| Deactivate | Soft-disable login without deleting the record |

---

## 7. Routes

```
# Petty Cash
GET    /petty-cash                  → PettyCashController@index
GET    /petty-cash/create           → PettyCashController@create
POST   /petty-cash                  → PettyCashController@store
GET    /petty-cash/{voucher}        → PettyCashController@show
PATCH  /petty-cash/{voucher}/acknowledge → PettyCashController@acknowledge
PATCH  /petty-cash/{voucher}/settle      → PettyCashController@settle
DELETE /petty-cash/{voucher}        → PettyCashController@destroy  [admin only]

# Reports
GET    /reports                     → ReportController@index
GET    /reports/inventory/{type}    → ReportController@inventory
GET    /reports/petty-cash/{type}   → ReportController@pettyCash
GET    /petty-cash/{voucher}/print  → PettyCashController@print

# User Management
GET    /users                       → UserController@index   [admin only]
GET    /users/create                → UserController@create  [admin only]
POST   /users                       → UserController@store   [admin only]
GET    /users/{user}/edit           → UserController@edit    [admin only]
PATCH  /users/{user}                → UserController@update  [admin only]
PATCH  /users/{user}/deactivate     → UserController@deactivate [admin only]
```

---

## 8. New Files

### Migrations
- `add_role_to_users_table`
- `create_petty_cash_vouchers_table`
- `create_petty_cash_items_table`

### Models
- `App\Models\PettyCashVoucher`
- `App\Models\PettyCashItem`

### Controllers
- `App\Http\Controllers\PettyCashController`
- `App\Http\Controllers\ReportController`
- `App\Http\Controllers\UserController`

### Middleware
- `App\Http\Middleware\EnsureRole`

### Views
- `resources/views/petty-cash/index.blade.php`
- `resources/views/petty-cash/create.blade.php`
- `resources/views/petty-cash/show.blade.php`
- `resources/views/petty-cash/print.blade.php`
- `resources/views/reports/index.blade.php`
- `resources/views/users/index.blade.php`
- `resources/views/users/create.blade.php`
- `resources/views/users/edit.blade.php`

### Updated Files
- `database/migrations/*_create_users_table.php` (add role column via new migration)
- `app/Models/User.php` (add role, isAdmin(), isAccounting(), isStaff() helpers)
- `app/Http/Controllers/DashboardController.php` (petty cash stats)
- `resources/views/dashboard.blade.php` (new tiles + recent vouchers)
- `resources/views/layouts/app.blade.php` (sidebar nav links + badges + role-gated items)
- `routes/web.php` (new route groups)

---

## 9. Testing

- `PettyCashVoucherTest` — submission creates inventory transactions, change computed correctly, overspend blocked
- `RoleMiddlewareTest` — staff cannot access settle/reports, accounting cannot create vouchers, admin can do all
- `UserManagementTest` — only admin can create/edit users
- `ReportControllerTest` — returns correct data for each report type
- `DashboardTest` (extend existing) — petty cash tiles present with correct values

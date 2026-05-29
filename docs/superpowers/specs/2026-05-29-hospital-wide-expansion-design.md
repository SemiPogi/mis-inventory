# Hospital-Wide Expansion — Design Spec
**Date:** 2026-05-29
**Project:** MIS Inventory — La Union Medical Center
**Scope:** Phase B — Multi-department hospital-wide system

---

## 1. Overview

Expand the current MIS-only inventory system into a hospital-wide platform where every department manages its own inventory and petty cash. The **Supply and Property Section** acts as the central stock hub, fulfilling requests from all departments via a digital **Requisition and Issue Slip (RIS)** workflow that mirrors the existing paper form (PAS-007-95).

---

## 2. Core Concept

| Actor | Role |
|---|---|
| **Supply Department** | Central hub. Receives stock from external suppliers (IAR). Fulfills RIS requests from all departments. |
| **All Departments** | Equal members. Each has its own inventory, petty cash, dashboard, and reports. |
| **Department Head** | A designated staff member per department who approves RIS requests and outgoing transfers. |
| **Accounting** | Hospital-wide. Settles petty cash vouchers for all departments. |
| **Admin** | God-mode. Creates departments, manages all users, sees all data hospital-wide. |

---

## 3. Architecture — Option A (department_id on all tables)

A single database with `department_id` foreign key added to all existing tables. The Supply department is identified by an `is_supply_hub` flag. This is the simplest extension of the current codebase.

### 3.1 Database Changes

**Modified tables:**
- `users` — add `department_id` (FK), `is_head` (boolean, designates dept head)
- `items` — add `department_id` (FK), `category` (string), `expiry_date` (nullable date), `min_stock_qty` (integer, default 0)
- `transactions` — add `department_id` (FK)
- `petty_cash_vouchers` — add `department_id` (FK)

**New tables:**

```
departments
  id, name, code (e.g. MIS, NURS, PHARM), is_supply_hub (bool),
  is_active (bool), created_at, updated_at

ris_requests
  id, ris_number (RIS-YYYY-NNNN), requesting_dept_id (FK),
  status (draft|pending_head|pending_supply|issued|completed|rejected),
  purpose, requested_by_id, head_approved_by_id, head_approved_at,
  supply_approved_by_id, supply_approved_at,
  issued_by_id, issued_at, acknowledged_by_id, acknowledged_at,
  notes, created_at, updated_at

ris_items
  id, ris_request_id (FK), stock_no, item_name, unit,
  requested_qty, issued_qty (nullable — set by Supply), remarks

department_transfers
  id, transfer_number (TRF-YYYY-NNNN), from_dept_id (FK), to_dept_id (FK),
  status (pending_head|in_transit|completed|rejected),
  initiated_by_id, approved_by_head_id, approved_at,
  acknowledged_by_id, acknowledged_at,
  notes, created_at, updated_at

department_transfer_items
  id, transfer_id (FK), item_id (FK), qty, unit

assemblies
  id, assembly_number (ASM-YYYY-NNNN), department_id (FK),
  output_item_name, output_qty, output_unit,
  assembled_by_id, assembled_at, notes, created_at, updated_at

assembly_components
  id, assembly_id (FK), item_id (FK), qty_consumed, unit

iar_records  (Supply department only)
  id, iar_number (IAR-YYYY-NNNN), department_id (FK, always Supply),
  supplier_name, date_received, received_by_id,
  notes, created_at, updated_at

iar_items
  id, iar_id (FK), item_name, unit, qty, unit_cost, total_cost,
  expiry_date (nullable), remarks

attachments  (polymorphic)
  id, attachable_type, attachable_id,
  file_path, original_name, mime_type, size_kb,
  uploaded_by_id, created_at

notifications
  id, user_id (FK), type, title, message,
  url (nullable — link to relevant record),
  read_at (nullable), created_at
```

---

## 4. The 4 Core Flows

### 4.1 RIS — Requisition and Issue Slip

Mirrors PAS-007-95 form exactly.

**Statuses:** `draft` → `pending_head` → `pending_supply` → `issued` → `completed`

**Steps:**
1. **Staff creates RIS** — selects items, quantities, states purpose. System auto-generates RIS number (RIS-YYYY-NNNN).
2. **Dept Head approves** — reviews the request. Can approve or reject with remarks. (`pending_head` → `pending_supply` or `rejected`)
3. **Supply reviews** — sees all pending RIS in a queue. Sets `issued_qty` per item (can be less than requested). Approves the release.
4. **Supply marks Issued** — items deducted from Supply inventory. Status → `issued`. Requesting dept notified.
5. **Dept acknowledges receipt** — staff confirms items received. Items added to dept inventory. Photo upload available. Status → `completed`. RIS now printable with all signature fields.

**Notes:**
- Partial fulfillment allowed: `issued_qty` can differ from `requested_qty`
- Rejected RIS shows Supply's remarks to the requesting dept
- Printable RIS form matches PAS-007-95 layout with 4 signature blocks

### 4.2 Department-to-Department Transfer

**Statuses:** `pending_head` → `in_transit` → `completed`

**Steps:**
1. **Staff initiates transfer** — selects items from their inventory, selects destination department, adds notes.
2. **Dept Head (sender) approves** — confirms items are leaving their department. (`pending_head` → `in_transit`)
3. **Receiving dept acknowledges** — confirms items received. Items deducted from sender, added to receiver. Status → `completed`.

**Notes:**
- Transfer number auto-generated (TRF-YYYY-NNNN)
- Photo upload available on acknowledgement
- Full audit trail: who sent, who approved, who received, timestamps

### 4.3 Assembly — Combine Items into a New Item

**Steps:**
1. **Staff selects component items** — picks items from their dept inventory and quantities to consume (e.g. 1x CPU, 1x RAM, 1x HDD, 1x Case)
2. **Names the output item** — (e.g. "Assembled Desktop PC Unit #1"), sets output qty and unit
3. **Confirms assembly** — components deducted from inventory, new item created in inventory with full assembly audit trail

**Notes:**
- Assembly number auto-generated (ASM-YYYY-NNNN)
- Output item appears in dept inventory as a regular item
- Assembly log shows all components used — useful for asset tracking
- Assembled items can be transferred to other departments

### 4.4 Petty Cash

Same workflow as current system, now tagged to a department.

**Changes:**
- Each voucher has a `department_id`
- Dept staff creates voucher → dept staff acknowledges → Accounting settles
- Accounting sees all departments' vouchers in one ledger
- Reports are filterable by department
- Department reports show only their own vouchers

---

## 5. Supply Department — Special Features

### 5.1 IAR Tracking (Incoming from External Suppliers)
- Supply logs deliveries from external suppliers using an IAR record
- IAR number auto-generated (IAR-YYYY-NNNN)
- Line items per delivery with qty, unit cost, expiry date
- Photo upload for delivery receipts
- Items automatically added to Supply's master inventory on save

### 5.2 RIS Queue
- Supply sees all incoming RIS requests across all departments in a single queue
- Can filter by department, date, status
- Sets issued qty per line item before issuing

### 5.3 Low Stock Alerts
- Each item has a `min_stock_qty` threshold
- Supply dashboard flags items below threshold
- Low stock report shows all items at or below minimum

---

## 6. Department Head Designation

- `is_head` boolean on the `users` table (no new role needed)
- Each department should have at least one head designated
- Admin assigns head status when creating/editing a user
- Head has all Staff permissions PLUS:
  - Approve/reject RIS requests from their department
  - Approve/reject outgoing transfers from their department
- If a dept has no head, Admin acts as fallback approver

---

## 7. Additional Features

### 7.1 Item Categories
- `category` string field on items (e.g. Office Supplies, Medical Supplies, IT Equipment, Equipment, Furniture, Others)
- Filterable in inventory list and reports
- Admin can define a global category list

### 7.2 Expiry Date Tracking
- `expiry_date` nullable date on items
- Set when receiving via RIS acknowledgement or IAR
- Inventory list shows expiry badges (red = expired, amber = within 30 days, green = ok)
- Expiry report per department and hospital-wide

### 7.3 In-App Notifications
Bell icon in sidebar with unread count. Notifications sent for:

| Event | Who gets notified |
|---|---|
| RIS created | Dept Head (approval needed) |
| RIS head-approved | Supply staff (new request in queue) |
| RIS issued | Requesting dept staff (items ready) |
| RIS completed | RIS creator |
| RIS rejected | RIS creator |
| Transfer initiated | Dept Head (approval needed) |
| Transfer approved | Receiving dept staff |
| Transfer acknowledged | Transfer initiator |
| Item below min stock | Supply staff |
| Item expiring in 30 days | Dept staff |
| Petty cash settled | Voucher creator |

### 7.4 Printable RIS Form
- Generates a print-ready page matching PAS-007-95 layout
- Shows all 4 signature blocks: Requested by / Approved by / Issued by / Received by
- Includes: RIS number, date, division, office, responsibility center code, items table with stock no / unit / description / req qty / issued qty / remarks
- Only available after status = `completed`

### 7.5 Photo/Document Upload
- Polymorphic `attachments` table — works on any record
- Supported on: RIS acknowledgement, IAR records, transfer acknowledgement, petty cash vouchers
- Multiple photos per record
- Displayed as thumbnails with lightbox view

---

## 8. Roles Summary

| Role | Department | Permissions |
|---|---|---|
| `admin` | None (hospital-wide) | Full access to everything. Creates departments. Manages all users. |
| `staff` | Assigned to one dept | Inventory, RIS, transfers, assembly, petty cash — scoped to their dept only |
| `staff` + `is_head` | Assigned to one dept | Everything staff can do PLUS approve/reject RIS and outgoing transfers |
| `accounting` | None (hospital-wide) | Settles petty cash for all departments. Reads all petty cash reports. |
| Supply `staff` | Supply dept | Same as staff but also sees RIS queue and processes IAR deliveries |
| Supply `staff` + `is_head` | Supply dept | Supply staff PLUS approves/issues RIS requests |

---

## 9. Reports

### Per Department (own data only)
1. Current Stock Snapshot
2. RIS History (requests made + status)
3. Items Received via RIS
4. Transfer Log (sent and received)
5. Assembly Log
6. Expiry Report
7. Stock Movement (in/out ledger)
8. Petty Cash (their vouchers)

### Supply Department
1. Master Stock Snapshot
2. RIS Fulfillment Report (requested qty vs issued qty)
3. IAR Delivery Log
4. Low Stock Report
5. Department Consumption Report (which depts request what most)
6. Expiry Report (Supply stock)

### Accounting (all departments)
1. Voucher Ledger (all depts)
2. Monthly Summary by Department
3. Outstanding Changes
4. Item Purchase History
5. Petty Cash by Department (comparison)

### Admin (hospital-wide)
All of the above plus:
1. Cross-Department Inventory Summary
2. All RIS Requests
3. All Transfers
4. All Assemblies
5. Hospital-Wide Low Stock + Expiry
6. Department Resource Comparison

**All reports support:** date range filter, item/dept search, CSV export, print view.

---

## 10. Navigation Changes

- Sidebar shows department name/code next to user name
- Supply staff see additional "RIS Queue" and "IAR" menu items
- Dept heads see "Pending Approvals" badge on relevant menu items
- Admin sees a "Departments" menu item
- Notifications bell replaces current profile-only alerts

---

## 11. Out of Scope (Phase C)

- Procurement module (purchase orders to external suppliers)
- Barcode/QR scanning
- Email notifications (in-app only for now)
- Mobile app
- Hospital-wide item catalog shared across departments
- Budget allocation per department

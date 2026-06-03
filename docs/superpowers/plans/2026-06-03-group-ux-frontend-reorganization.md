# Group UX — Frontend Reorganization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace three separate dashboard alert cards with a single tabbed Alerts card, rewrite the flat sidebar into labeled collapsible sections (with localStorage state), and convert the item show page into a three-tab layout (Overview / History / Audit Log) with URL-hash navigation.

**Architecture:** Pure view-layer changes — no new routes, no controller changes. All three tasks touch only Blade templates and test files. Alpine.js `x-data` and `x-show` handle all interactivity; section collapse state is persisted in `localStorage`.

**Tech Stack:** Laravel 13 Blade, Alpine.js 3, Tailwind CSS, PHPUnit Feature tests

---

## Context for subagent

**Repo:** `/Users/bayanestonilo/Sites/mis-inventory`
**Test command:** `php artisan test --filter=<TestClass>` or `php artisan test` for full suite
**Current test count:** 176 passing
**Branch:** `main`

**Key files you'll touch:**
- `resources/views/dashboard.blade.php` — three separate alert cards on lines 86–181
- `resources/views/layouts/app.blade.php` — flat sidebar nav starting at line 82; `$nav` array at lines 18–25; root `x-data` on line 82
- `resources/views/items-show.blade.php` — single-page layout, no tabs
- `tests/Feature/ExpiryTest.php` — `assertSee('Expiry Alerts')` on line 205, `assertDontSee('Expiry Alerts')` on line 226
- `tests/Feature/LowStockTest.php` — `assertSee('Low Stock Alerts')` on line 65, `assertDontSee('Low Stock Alerts')` on line 79
- `tests/Feature/WarrantyTest.php` — `assertSee('Warranty Alerts')` on line 178, `assertDontSee('Warranty Alerts')` on line 193

---

## File Map

| File | Action | Task |
|---|---|---|
| `resources/views/dashboard.blade.php` | Modify lines 86–181 | UX1 |
| `tests/Feature/DashboardAlertsTabTest.php` | Create | UX1 |
| `tests/Feature/ExpiryTest.php` | Update 2 assertions | UX1 |
| `tests/Feature/LowStockTest.php` | Update 2 assertions | UX1 |
| `tests/Feature/WarrantyTest.php` | Update 2 assertions | UX1 |
| `resources/views/layouts/app.blade.php` | Modify @php block + nav | UX2 |
| `tests/Feature/SidebarGroupsTest.php` | Create | UX2 |
| `resources/views/items-show.blade.php` | Full rewrite into tabs | UX3 |
| `tests/Feature/ItemsShowTabsTest.php` | Create | UX3 |

---

## Task UX1 — Dashboard Unified Alerts Card

**Files:**
- Modify: `resources/views/dashboard.blade.php` (lines 86–181)
- Create: `tests/Feature/DashboardAlertsTabTest.php`
- Modify: `tests/Feature/ExpiryTest.php` (2 lines)
- Modify: `tests/Feature/LowStockTest.php` (2 lines)
- Modify: `tests/Feature/WarrantyTest.php` (2 lines)

### What changes

Lines 86–181 of `dashboard.blade.php` currently have three separate `x-bento-card` blocks:
- "Expiry Alerts" (lines 86–115)
- "Low Stock Alerts" (lines 117–147)
- "Warranty Alerts" (lines 149–181)

Replace all three with a single tabbed card that shows only the tabs that have data. The controller (`DashboardController`) already provides `$expiringItems`, `$lowStockItems`, and `$warrantyItems` — **no controller changes needed**.

---

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/DashboardAlertsTabTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAlertsTabTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D'   . self::$seq,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'admin',
            'name'          => 'Test Admin',
            'department_id' => $dept->id,
        ]);
    }

    private function makeItem(Department $dept, array $overrides = []): Item
    {
        self::$seq++;
        return Item::create(array_merge([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $dept->id,
        ], $overrides));
    }

    /** @test */
    public function test_dashboard_shows_unified_alerts_card_with_expiry_tab(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        $this->makeItem($dept, ['expiry_date' => now()->addDays(10)->toDateString()]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alerts')
            ->assertSee('Expiry (');
    }

    /** @test */
    public function test_dashboard_shows_unified_alerts_card_with_low_stock_tab(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        $this->makeItem($dept, ['current_qty' => 2, 'min_stock_qty' => 10]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alerts')
            ->assertSee('Low Stock (');
    }

    /** @test */
    public function test_dashboard_shows_unified_alerts_card_with_warranty_tab(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        $this->makeItem($dept, ['warranty_expiry_date' => now()->addDays(30)->toDateString()]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Alerts')
            ->assertSee('Warranty (');
    }

    /** @test */
    public function test_dashboard_hides_alerts_card_when_no_alerts(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        $this->makeItem($dept); // plain item — no expiry, no warranty, no low-stock threshold

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Expiry (')
            ->assertDontSee('Low Stock (')
            ->assertDontSee('Warranty (');
    }

    /** @test */
    public function test_dashboard_hides_tab_when_no_items_in_that_category(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin($dept);
        // Only low stock — expiry and warranty tabs must not appear
        $this->makeItem($dept, ['current_qty' => 1, 'min_stock_qty' => 10]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Low Stock (')
            ->assertDontSee('Expiry (')
            ->assertDontSee('Warranty (');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
php artisan test --filter=DashboardAlertsTabTest
```

Expected: 5 failures — "Alerts", "Expiry (", "Low Stock (", "Warranty (" not found because old cards say "Expiry Alerts", "Low Stock Alerts", "Warranty Alerts".

- [ ] **Step 3: Replace the three separate alert cards with one unified tabbed card**

In `resources/views/dashboard.blade.php`, replace the block from `{{-- Expiry Alert --}}` (line 86) through the closing `@endif` of Warranty Alerts (line 181) with:

```blade
    {{-- ── Unified Alerts Card ──────────────────────────────────────── --}}
    @php
        $hasAnyAlert = $expiringItems->isNotEmpty()
                    || $lowStockItems->isNotEmpty()
                    || $warrantyItems->isNotEmpty();
        $defaultTab  = $expiringItems->isNotEmpty()  ? 'expiry'
                     : ($lowStockItems->isNotEmpty()  ? 'low-stock' : 'warranty');
    @endphp
    @if($hasAnyAlert)
    <x-bento-card :padded="false" class="mb-4"
        x-data="{ tab: '{{ $defaultTab }}' }">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <x-heroicon-o-exclamation-triangle class="w-4 h-4 text-amber-500"/>
                    <h2 class="text-sm font-semibold text-ink-heading">Alerts</h2>
                </div>
                <div class="flex gap-1">
                    @if($expiringItems->isNotEmpty())
                        <button @click="tab = 'expiry'"
                                :class="tab === 'expiry' ? 'bg-amber-100 text-amber-700' : 'text-ink-muted hover:text-ink-heading'"
                                class="px-3 py-1 rounded-full text-xs font-medium transition">
                            Expiry ({{ $expiringItems->count() }})
                        </button>
                    @endif
                    @if($lowStockItems->isNotEmpty())
                        <button @click="tab = 'low-stock'"
                                :class="tab === 'low-stock' ? 'bg-amber-100 text-amber-700' : 'text-ink-muted hover:text-ink-heading'"
                                class="px-3 py-1 rounded-full text-xs font-medium transition">
                            Low Stock ({{ $lowStockItems->count() }})
                        </button>
                    @endif
                    @if($warrantyItems->isNotEmpty())
                        <button @click="tab = 'warranty'"
                                :class="tab === 'warranty' ? 'bg-amber-100 text-amber-700' : 'text-ink-muted hover:text-ink-heading'"
                                class="px-3 py-1 rounded-full text-xs font-medium transition">
                            Warranty ({{ $warrantyItems->count() }})
                        </button>
                    @endif
                </div>
            </div>
            <a href="{{ route('items.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all items</a>
        </div>

        {{-- Expiry tab --}}
        @if($expiringItems->isNotEmpty())
        <div x-show="tab === 'expiry'">
            <x-table :headers="['Item', 'Category', 'Stock', 'Expiry Date', 'Status']">
                @foreach($expiringItems as $ei)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">
                            <a href="{{ route('items.show', $ei) }}" class="hover:text-primary-600">{{ $ei->name }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $ei->category ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $ei->current_qty }} {{ $ei->unit }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $ei->expiry_date->format('M d, Y') }}</td>
                        <td class="px-6 py-3">
                            @if($ei->expiryStatus() === 'expired')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-700">Expired</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Expires {{ $ei->expiry_date->diffForHumans() }}</span>
                            @endif
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>
        @endif

        {{-- Low Stock tab --}}
        @if($lowStockItems->isNotEmpty())
        <div x-show="tab === 'low-stock'">
            <x-table :headers="['Item', 'Category', 'Current Stock', 'Min Stock', 'Status']">
                @foreach($lowStockItems as $ls)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">
                            <a href="{{ route('items.show', $ls) }}" class="hover:text-primary-600">{{ $ls->name }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $ls->category ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $ls->current_qty }} {{ $ls->unit }}</td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $ls->min_stock_qty }} {{ $ls->unit }}</td>
                        <td class="px-6 py-3">
                            @if($ls->current_qty === 0)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-700">Out of Stock</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">Low Stock</span>
                            @endif
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>
        @endif

        {{-- Warranty tab --}}
        @if($warrantyItems->isNotEmpty())
        <div x-show="tab === 'warranty'">
            <x-table :headers="['Item', 'Category', 'Provider', 'Warranty Expiry', 'Status']">
                @foreach($warrantyItems as $wi)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">
                            <a href="{{ route('items.show', $wi) }}" class="hover:text-primary-600">{{ $wi->name }}</a>
                        </td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $wi->category ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-ink-muted">{{ $wi->warranty_provider ?? '—' }}</td>
                        <td class="px-6 py-3 text-sm text-ink-body">{{ $wi->warranty_expiry_date->format('M d, Y') }}</td>
                        <td class="px-6 py-3">
                            @if($wi->warrantyStatus() === 'expired')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-700">Expired</span>
                            @elseif($wi->warrantyStatus() === 'expiring')
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-700">Expiring soon</span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">{{ $wi->warranty_expiry_date->diffForHumans() }}</span>
                            @endif
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>
        @endif

    </x-bento-card>
    @endif
```

- [ ] **Step 4: Update the three existing test files whose assertions now refer to removed card headers**

**`tests/Feature/ExpiryTest.php`** — find these two lines and change them:

```php
// OLD (test_dashboard_shows_expiry_alert_for_expiring_items):
$response->assertSee('Expiry Alerts');

// NEW:
$response->assertSee('Expiry (');
```

```php
// OLD (test_dashboard_hides_expiry_section_when_none):
$response->assertDontSee('Expiry Alerts');

// NEW:
$response->assertDontSee('Expiry (');
```

**`tests/Feature/LowStockTest.php`** — find these two lines and change them:

```php
// OLD (test_dashboard_shows_low_stock_items):
->assertSee('Low Stock Alerts')

// NEW:
->assertSee('Low Stock (')
```

```php
// OLD (test_dashboard_does_not_show_low_stock_section_when_min_stock_zero):
->assertDontSee('Low Stock Alerts');

// NEW:
->assertDontSee('Low Stock (');
```

**`tests/Feature/WarrantyTest.php`** — find these two lines and change them:

```php
// OLD (test_dashboard_shows_warranty_expiring_items):
->assertSee('Warranty Alerts');

// NEW:
->assertSee('Warranty (');
```

```php
// OLD (test_dashboard_hides_warranty_alerts_when_no_expiring_items):
->assertDontSee('Warranty Alerts');

// NEW:
->assertDontSee('Warranty (');
```

- [ ] **Step 5: Run all affected tests and verify they pass**

```bash
php artisan test --filter="DashboardAlertsTabTest|ExpiryTest|LowStockTest|WarrantyTest"
```

Expected: all pass. If any `assertDontSee` test still fails, the old card header text may still be present — double-check that lines 86–181 were fully replaced and no old `h2` text remains.

- [ ] **Step 6: Commit**

```bash
git add resources/views/dashboard.blade.php \
        tests/Feature/DashboardAlertsTabTest.php \
        tests/Feature/ExpiryTest.php \
        tests/Feature/LowStockTest.php \
        tests/Feature/WarrantyTest.php
git commit -m "feat: unified tabbed Alerts card on dashboard (UX1)"
```

---

## Task UX2 — Sidebar Grouped Collapsible Sections

**Files:**
- Modify: `resources/views/layouts/app.blade.php`
- Create: `tests/Feature/SidebarGroupsTest.php`

### What changes

The sidebar in `app.blade.php` is a flat list of ~40 links. Replace it with labeled section groups (INVENTORY, APPROVALS, REQUISITIONS, OPERATIONS, FINANCE, ADMIN). Each section:
- Has a toggle button (visible in expanded sidebar) that collapses/expands the group, state saved to `localStorage`
- Shows a thin horizontal divider (visible in icon-only collapsed sidebar)
- Wraps its nav items in `x-show="collapsed || s<Section>"` — in icon-only mode items always show, in expanded mode they show only if section is open

The outer `x-data` on `<div class="flex min-h-screen">` gains 6 new section state properties (`sInventory`, `sApprovals`, `sRequisitions`, `sOperations`, `sFinance`, `sAdmin`). The `$nav` PHP array (lines 18–25) is removed since items are now written explicitly by group.

**APPROVALS combined badge:** `$approvalBadge + $risHeadBadge + $transferHeadBadge` — add this computed `$combinedApprovalBadge` to the `@php` block. Shown on section header in expanded mode.

**Section visibility** (Blade `@if`, not `x-show` — so HTML is not emitted for unauthorized users):
- APPROVALS: only when `$user->is_head || $user->isAdmin()`
- ADMIN: only when `$user->canManageUsers() || $user->canAccessReports()`
- All other sections: always rendered

---

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/SidebarGroupsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SidebarGroupsTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D'   . self::$seq,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function test_sidebar_shows_all_sections_for_admin(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create([
            'role'          => 'admin',
            'name'          => 'Test Admin',
            'department_id' => $dept->id,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Inventory')
            ->assertSee('Approvals')
            ->assertSee('Requisitions')
            ->assertSee('Operations')
            ->assertSee('Finance')
            ->assertSee('Admin');
    }

    /** @test */
    public function test_sidebar_shows_basic_sections_for_staff(): void
    {
        $dept  = $this->makeDept();
        $staff = User::factory()->create([
            'role'          => 'staff',
            'name'          => 'Test Staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Inventory')
            ->assertSee('Requisitions')
            ->assertSee('Operations')
            ->assertSee('Finance');
    }

    /** @test */
    public function test_sidebar_hides_approvals_section_for_staff(): void
    {
        $dept  = $this->makeDept();
        $staff = User::factory()->create([
            'role'          => 'staff',
            'name'          => 'Test Staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Approvals<');
    }

    /** @test */
    public function test_sidebar_hides_admin_section_for_staff(): void
    {
        $dept  = $this->makeDept();
        $staff = User::factory()->create([
            'role'          => 'staff',
            'name'          => 'Test Staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('>Admin<');
    }

    /** @test */
    public function test_sidebar_shows_approvals_section_for_head(): void
    {
        $dept = $this->makeDept();
        $head = User::factory()->create([
            'role'          => 'staff',
            'name'          => 'Test Head',
            'department_id' => $dept->id,
            'is_head'       => true,
        ]);

        $this->actingAs($head)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Approvals');
    }
}
```

Note: the tests for `assertDontSee` use `'>Approvals<'` and `'>Admin<'` (with `>` and `<` surrounding the text) to match the literal `<span>Approvals</span>` in the section header, avoiding false matches from role text elsewhere in the page.

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test --filter=SidebarGroupsTest
```

Expected: failures — "Inventory", "Approvals", "Requisitions", etc. not found because the current sidebar has no section headers.

- [ ] **Step 3: Update the root div `x-data` and add `$combinedApprovalBadge` to the `@php` block**

In `resources/views/layouts/app.blade.php`:

**Change line 82** from:
```blade
<div class="flex min-h-screen" x-data="{ collapsed: localStorage.getItem('sidebar-collapsed') === '1' }">
```
to:
```blade
<div class="flex min-h-screen" x-data="{
    collapsed:    localStorage.getItem('sidebar-collapsed') === '1',
    sInventory:   localStorage.getItem('nav-inventory')    !== '0',
    sApprovals:   localStorage.getItem('nav-approvals')    !== '0',
    sRequisitions:localStorage.getItem('nav-requisitions') !== '0',
    sOperations:  localStorage.getItem('nav-operations')   !== '0',
    sFinance:     localStorage.getItem('nav-finance')      !== '0',
    sAdmin:       localStorage.getItem('nav-admin')        !== '0',
}">
```

**Add to the bottom of the `@php` block** (before `@endphp` on line 80):
```php
    $combinedApprovalBadge = $approvalBadge + $risHeadBadge + $transferHeadBadge;
```

**Remove** the `$nav` array lines 18–25:
```php
// DELETE these lines:
$nav = [
    ['route' => 'dashboard',          'label' => 'Dashboard',    'icon' => 'home',                    'match' => '/'],
    ['route' => 'receive.index',      'label' => 'Receive',      'icon' => 'arrow-down-tray',         'match' => 'receive'],
    ['route' => 'release.index',      'label' => 'Release',      'icon' => 'arrow-up-tray',           'match' => 'release'],
    ['route' => 'acknowledge.index',  'label' => 'Acknowledge',  'icon' => 'check-circle',            'match' => 'acknowledge'],
    ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'clipboard-document-list', 'match' => 'transactions*'],
    ['route' => 'items.index',        'label' => 'Inventory',    'icon' => 'cube',                    'match' => 'items*'],
];
```

- [ ] **Step 4: Replace the entire `<nav>` block with grouped sections**

Replace everything between `<nav class="flex-1 px-3 py-4 space-y-1">` and `</nav>` (lines 99–345) with:

```blade
        <nav class="flex-1 px-3 py-4">

            {{-- Dashboard — always visible, no section header --}}
            @php $active = request()->is('/'); @endphp
            <a href="{{ route('dashboard') }}"
               class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition mb-1
                      {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
               title="Dashboard">
                @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                <x-heroicon-o-home class="w-5 h-5 shrink-0"/>
                <span x-show="!collapsed" x-transition.opacity>Dashboard</span>
            </a>

            {{-- ── INVENTORY ── --}}
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sInventory = !sInventory; localStorage.setItem('nav-inventory', sInventory ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Inventory</span>
                    <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" :class="{ '-rotate-90': !sInventory }"/>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sInventory" class="space-y-0.5 mt-0.5">
                    @php $active = request()->is('receive*'); @endphp
                    <a href="{{ route('receive.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Receive">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-arrow-down-tray class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Receive</span>
                    </a>
                    @php $active = request()->is('release*'); @endphp
                    <a href="{{ route('release.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Release">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-arrow-up-tray class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Release</span>
                    </a>
                    @php $active = request()->is('acknowledge*'); @endphp
                    <a href="{{ route('acknowledge.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Acknowledge">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-check-circle class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Acknowledge</span>
                    </a>
                    @php $active = request()->routeIs('transactions*'); @endphp
                    <a href="{{ route('transactions.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Transactions">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Transactions</span>
                    </a>
                    @php $active = request()->routeIs('items*'); @endphp
                    <a href="{{ route('items.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $active ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Inventory">
                        @if($active)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-cube class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Inventory</span>
                    </a>
                </div>
            </div>

            {{-- ── APPROVALS (head + admin only) ── --}}
            @if($user->is_head || $user->isAdmin())
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sApprovals = !sApprovals; localStorage.setItem('nav-approvals', sApprovals ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <div class="flex items-center gap-1.5">
                        <span>Approvals</span>
                        @if($combinedApprovalBadge > 0)
                            <span class="bg-amber-500 text-white text-[10px] rounded-full px-1.5 leading-4 font-semibold">
                                {{ $combinedApprovalBadge > 9 ? '9+' : $combinedApprovalBadge }}
                            </span>
                        @endif
                    </div>
                    <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" :class="{ '-rotate-90': !sApprovals }"/>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sApprovals" class="space-y-0.5 mt-0.5">
                    @php $approvalsActive = request()->routeIs('approvals.*'); @endphp
                    <a href="{{ route('approvals.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $approvalsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Approvals">
                        @if($approvalsActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
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
                    @php $headActive = request()->routeIs('ris.head.*'); @endphp
                    <a href="{{ route('ris.head.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $headActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="RIS Approvals">
                        @if($headActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <span class="relative shrink-0">
                            <x-heroicon-o-check-badge class="w-5 h-5"/>
                            @if($risHeadBadge > 0)
                                <span class="absolute -top-1 -right-1 bg-purple-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                    {{ $risHeadBadge > 9 ? '9+' : $risHeadBadge }}
                                </span>
                            @endif
                        </span>
                        <span x-show="!collapsed" x-transition.opacity>RIS Approvals</span>
                    </a>
                    @php $tHeadActive = request()->routeIs('transfers.head.*'); @endphp
                    <a href="{{ route('transfers.head.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $tHeadActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Transfer Approvals">
                        @if($tHeadActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <span class="relative shrink-0">
                            <x-heroicon-o-check-badge class="w-5 h-5"/>
                            @if($transferHeadBadge > 0)
                                <span class="absolute -top-1 -right-1 bg-purple-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                    {{ $transferHeadBadge > 9 ? '9+' : $transferHeadBadge }}
                                </span>
                            @endif
                        </span>
                        <span x-show="!collapsed" x-transition.opacity>Transfer Approvals</span>
                    </a>
                </div>
            </div>
            @endif

            {{-- ── REQUISITIONS ── --}}
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sRequisitions = !sRequisitions; localStorage.setItem('nav-requisitions', sRequisitions ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Requisitions</span>
                    <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" :class="{ '-rotate-90': !sRequisitions }"/>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sRequisitions" class="space-y-0.5 mt-0.5">
                    @php $myRisActive = request()->routeIs('ris.index') || request()->routeIs('ris.show') || request()->routeIs('ris.create'); @endphp
                    <a href="{{ route('ris.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $myRisActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="My RIS">
                        @if($myRisActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-clipboard-document-list class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>My RIS</span>
                    </a>
                    @if($user->isAdmin() || ($supplyHub && $user->department_id === $supplyHub->id))
                        @php $supplyActive = request()->routeIs('ris.supply.*'); @endphp
                        <a href="{{ route('ris.supply.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $supplyActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Supply Queue">
                            @if($supplyActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <span class="relative shrink-0">
                                <x-heroicon-o-inbox-stack class="w-5 h-5"/>
                                @if($risSupplyBadge > 0)
                                    <span class="absolute -top-1 -right-1 bg-blue-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                        {{ $risSupplyBadge > 9 ? '9+' : $risSupplyBadge }}
                                    </span>
                                @endif
                            </span>
                            <span x-show="!collapsed" x-transition.opacity>Supply Queue</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- ── OPERATIONS ── --}}
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sOperations = !sOperations; localStorage.setItem('nav-operations', sOperations ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Operations</span>
                    <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" :class="{ '-rotate-90': !sOperations }"/>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sOperations" class="space-y-0.5 mt-0.5">
                    @php $transferActive = request()->routeIs('transfers.*') && !request()->routeIs('transfers.head.*'); @endphp
                    <a href="{{ route('transfers.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $transferActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Transfers">
                        @if($transferActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-arrows-right-left class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Transfers</span>
                    </a>
                    @php $asmActive = request()->routeIs('assemblies.*'); @endphp
                    <a href="{{ route('assemblies.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $asmActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Assemblies">
                        @if($asmActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <x-heroicon-o-wrench-screwdriver class="w-5 h-5 shrink-0"/>
                        <span x-show="!collapsed" x-transition.opacity>Assemblies</span>
                    </a>
                    @if($user->isAdmin() || ($supplyHub && $user->department_id === $supplyHub->id))
                        @php $iarActive = request()->routeIs('iar.*'); @endphp
                        <a href="{{ route('iar.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $iarActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="IAR Records">
                            @if($iarActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-document-check class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>IAR Records</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- ── FINANCE ── --}}
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sFinance = !sFinance; localStorage.setItem('nav-finance', sFinance ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Finance</span>
                    <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" :class="{ '-rotate-90': !sFinance }"/>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sFinance" class="space-y-0.5 mt-0.5">
                    @php $pcActive = request()->is('petty-cash*'); @endphp
                    <a href="{{ route('petty-cash.index') }}"
                       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                              {{ $pcActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                       title="Petty Cash">
                        @if($pcActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                        <span class="relative shrink-0">
                            <x-heroicon-o-banknotes class="w-5 h-5"/>
                            @if($pcBadge > 0)
                                <span class="absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                    {{ $pcBadge > 9 ? '9+' : $pcBadge }}
                                </span>
                            @endif
                        </span>
                        <span x-show="!collapsed" x-transition.opacity>Petty Cash</span>
                    </a>
                </div>
            </div>

            {{-- ── ADMIN (admin + accounting) ── --}}
            @if($user->canManageUsers() || $user->canAccessReports())
            <div class="mt-2">
                <button x-show="!collapsed"
                        @click="sAdmin = !sAdmin; localStorage.setItem('nav-admin', sAdmin ? '1' : '0')"
                        class="flex items-center justify-between w-full px-3 py-1 text-[10px] font-semibold text-ink-muted uppercase tracking-wider hover:text-ink-heading transition">
                    <span>Admin</span>
                    <x-heroicon-o-chevron-down class="w-3 h-3 transition-transform" :class="{ '-rotate-90': !sAdmin }"/>
                </button>
                <div x-show="collapsed" class="border-t border-surface-border mx-2 my-1.5"></div>
                <div x-show="collapsed || sAdmin" class="space-y-0.5 mt-0.5">
                    @if($user->canAccessReports())
                        @php $repActive = request()->is('reports*'); @endphp
                        <a href="{{ route('reports.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $repActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Reports">
                            @if($repActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-chart-bar class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>Reports</span>
                        </a>
                    @endif
                    @if($user->canManageUsers())
                        @php $usersActive = request()->is('users*'); @endphp
                        <a href="{{ route('users.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $usersActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Users">
                            @if($usersActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-users class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>Users</span>
                        </a>
                        @php $deptsActive = request()->is('departments*'); @endphp
                        <a href="{{ route('departments.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $deptsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Departments">
                            @if($deptsActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-building-office class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>Departments</span>
                        </a>
                        @php $catsActive = request()->is('item-categories*'); @endphp
                        <a href="{{ route('item-categories.index') }}"
                           class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ $catsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                           title="Categories">
                            @if($catsActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                            <x-heroicon-o-tag class="w-5 h-5 shrink-0"/>
                            <span x-show="!collapsed" x-transition.opacity>Categories</span>
                        </a>
                    @endif
                </div>
            </div>
            @endif

            {{-- Notifications — standalone (always visible) --}}
            <div class="mt-2">
                @php $notifActive = request()->routeIs('notifications.*'); @endphp
                <a href="{{ route('notifications.index') }}"
                   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                          {{ $notifActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}"
                   title="Notifications">
                    @if($notifActive)<span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>@endif
                    <span class="relative shrink-0">
                        <x-heroicon-o-bell class="w-5 h-5"/>
                        @if($notifCount > 0)
                            <span class="absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center leading-none">
                                {{ $notifCount > 9 ? '9+' : $notifCount }}
                            </span>
                        @endif
                    </span>
                    <span x-show="!collapsed" x-transition.opacity>Notifications</span>
                </a>
            </div>

        </nav>
```

- [ ] **Step 5: Run all sidebar tests and verify they pass**

```bash
php artisan test --filter=SidebarGroupsTest
```

Expected: all 5 pass. If an `assertDontSee` fails, the section header `<span>` content might be rendered differently — check the exact HTML output with `dd($response->getContent())` and adjust.

- [ ] **Step 6: Run full test suite to catch any regressions from removing the `$nav` array**

```bash
php artisan test
```

Expected: all tests still pass (the `$nav` array was only used in the `@foreach` loop we removed).

- [ ] **Step 7: Commit**

```bash
git add resources/views/layouts/app.blade.php \
        tests/Feature/SidebarGroupsTest.php
git commit -m "feat: grouped collapsible sidebar sections with localStorage state (UX2)"
```

---

## Task UX3 — Item Show Page Tabbed Layout

**Files:**
- Modify: `resources/views/items-show.blade.php`
- Create: `tests/Feature/ItemsShowTabsTest.php`

### What changes

The item show page is currently a single long page. Wrap all content after the page header in a three-tab layout using Alpine.js with URL hash navigation:

| Tab | Hash | Content |
|---|---|---|
| Overview | `#overview` | 4 stat cards + expiry card + warranty card + sparkline |
| History | `#history` | Transaction History table |
| Audit Log | `#audit-log` | Audit log timeline |

The page header (name/subtitle/stock badges) and the back link stay **outside** the tab wrapper (always visible).

Tab state: `window.location.hash.slice(1)` on load, updated by `@hashchange.window` so browser back/forward work. Default is `'overview'`.

---

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ItemsShowTabsTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemsShowTabsTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D'   . self::$seq,
            'is_active' => true,
        ]);
    }

    private function makeItem(Department $dept, array $overrides = []): Item
    {
        self::$seq++;
        return Item::create(array_merge([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $dept->id,
        ], $overrides));
    }

    /** @test */
    public function test_items_show_has_three_tabs(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Overview')
            ->assertSee('History')
            ->assertSee('Audit Log');
    }

    /** @test */
    public function test_items_show_overview_tab_contains_stat_cards(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept, ['unit' => 'reams']);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Current Stock')
            ->assertSee('Total Received')
            ->assertSee('reams');
    }

    /** @test */
    public function test_items_show_history_tab_contains_transaction_table(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Transaction History');
    }

    /** @test */
    public function test_items_show_audit_log_tab_contains_log_section(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Audit Log');
    }
}
```

- [ ] **Step 2: Run tests to verify the failing one**

```bash
php artisan test --filter=ItemsShowTabsTest
```

Expected: `test_items_show_has_three_tabs` **fails** because "Overview" is not currently in the HTML. The other three may pass because "History" appears in "Transaction History" and "Audit Log" already exists as a section heading. This is fine — the fail on `assertSee('Overview')` confirms the tab nav isn't there yet.

- [ ] **Step 3: Rewrite `resources/views/items-show.blade.php` with tabs**

Replace the entire file content with:

```blade
<x-app-layout>
    <div class="mb-4">
        <a href="{{ route('items.index') }}" class="inline-flex items-center gap-1 text-sm text-primary-600 hover:text-primary-700">
            <x-heroicon-o-arrow-left class="w-4 h-4"/> Back to Inventory
        </a>
    </div>

    <x-page-header :title="$item->name"
                   :subtitle="trim(($item->brand ?? '') . ' ' . ($item->model_number ? '— ' . $item->model_number : '')) ?: null">
        <x-slot:actions>
            @if($item->current_qty === 0)
                <x-status-badge status="pending">Out of stock</x-status-badge>
            @elseif($item->isBelowMinStock())
                <x-status-badge status="released">{{ $item->current_qty }} {{ $item->unit }} in stock</x-status-badge>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-700">
                    Low Stock
                </span>
            @else
                <x-status-badge status="acknowledged">{{ $item->current_qty }} {{ $item->unit }} in stock</x-status-badge>
            @endif
        </x-slot:actions>
    </x-page-header>

    {{-- Tab layout --}}
    <div x-data="{
        tab: (window.location.hash ? window.location.hash.slice(1) : 'overview')
    }" @hashchange.window="tab = (window.location.hash ? window.location.hash.slice(1) : 'overview')">

        {{-- Tab navigation --}}
        <div class="flex gap-0 mb-4 border-b border-surface-border">
            <a href="#overview"
               @click.prevent="tab = 'overview'; window.location.hash = 'overview'"
               :class="tab === 'overview'
                   ? 'border-b-2 border-primary-600 text-primary-700 font-medium'
                   : 'border-b-2 border-transparent text-ink-muted hover:text-ink-heading'"
               class="px-4 py-2.5 text-sm transition -mb-px cursor-pointer">
                Overview
            </a>
            <a href="#history"
               @click.prevent="tab = 'history'; window.location.hash = 'history'"
               :class="tab === 'history'
                   ? 'border-b-2 border-primary-600 text-primary-700 font-medium'
                   : 'border-b-2 border-transparent text-ink-muted hover:text-ink-heading'"
               class="px-4 py-2.5 text-sm transition -mb-px cursor-pointer">
                History
            </a>
            <a href="#audit-log"
               @click.prevent="tab = 'audit-log'; window.location.hash = 'audit-log'"
               :class="tab === 'audit-log'
                   ? 'border-b-2 border-primary-600 text-primary-700 font-medium'
                   : 'border-b-2 border-transparent text-ink-muted hover:text-ink-heading'"
               class="px-4 py-2.5 text-sm transition -mb-px cursor-pointer">
                Audit Log
            </a>
        </div>

        {{-- ── Overview Tab ── --}}
        <div x-show="tab === 'overview'">

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                <x-bento-card>
                    <p class="text-xs text-ink-muted uppercase tracking-wide">Category</p>
                    <p class="font-medium text-ink-heading mt-1">{{ $item->category ?? '—' }}</p>
                </x-bento-card>
                <x-bento-card>
                    <p class="text-xs text-ink-muted uppercase tracking-wide">Serial No.</p>
                    <p class="font-medium text-ink-heading mt-1">{{ $item->serial_number ?? '—' }}</p>
                </x-bento-card>
                <x-bento-card>
                    <p class="text-xs text-ink-muted uppercase tracking-wide">Total Received</p>
                    <p class="font-medium text-ink-heading mt-1">{{ $item->total_qty_received }} {{ $item->unit }}</p>
                </x-bento-card>
                <x-bento-card>
                    <p class="text-xs text-ink-muted uppercase tracking-wide">Current Stock</p>
                    <p class="font-medium text-ink-heading mt-1" x-data x-count-up>{{ $item->current_qty }}</p>
                </x-bento-card>
            </div>

            @if($item->expiry_date)
            <div class="mb-4">
                <x-bento-card>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs text-ink-muted uppercase tracking-wide mb-1">Expiry Date</p>
                            <p class="text-lg font-semibold text-ink-heading">{{ $item->expiry_date->format('M d, Y') }}</p>
                            <p class="text-xs text-ink-muted mt-0.5">
                                @if($item->isExpired())
                                    Expired {{ $item->expiry_date->diffForHumans() }}
                                @else
                                    Expires {{ $item->expiry_date->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                        @if($item->expiryStatus() === 'expired')
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-rose-100 text-rose-700">Expired</span>
                        @elseif($item->expiryStatus() === 'soon')
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-amber-100 text-amber-700">Expiring soon</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-emerald-100 text-emerald-700">Valid</span>
                        @endif
                    </div>
                </x-bento-card>
            </div>
            @endif

            @if($item->warranty_provider || $item->warranty_expiry_date || $item->warranty_reference_no || $item->warranty_notes)
            <div class="mb-4">
                <x-bento-card>
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-3">
                                <x-heroicon-o-shield-check class="w-4 h-4 text-emerald-500"/>
                                <p class="text-xs text-ink-muted uppercase tracking-wide font-medium">Warranty</p>
                            </div>
                            @if($item->warranty_provider)
                                <p class="text-sm text-ink-muted">Warranty Provider</p>
                                <p class="font-medium text-ink-heading mb-2">{{ $item->warranty_provider }}</p>
                            @endif
                            @if($item->warranty_reference_no)
                                <p class="text-sm text-ink-muted">Reference No.</p>
                                <p class="font-medium text-ink-heading mb-2">{{ $item->warranty_reference_no }}</p>
                            @endif
                            @if($item->warranty_expiry_date)
                                <p class="text-sm text-ink-muted">Expires</p>
                                <p class="font-medium text-ink-heading mb-1">{{ $item->warranty_expiry_date->format('M d, Y') }}</p>
                                <p class="text-xs text-ink-muted">
                                    @if($item->warrantyStatus() === 'expired')
                                        Expired {{ $item->warranty_expiry_date->diffForHumans() }}
                                    @else
                                        Expires {{ $item->warranty_expiry_date->diffForHumans() }}
                                    @endif
                                </p>
                            @endif
                            @if($item->warranty_notes)
                                <p class="text-sm text-ink-muted mt-2">Coverage</p>
                                <p class="text-sm text-ink-body">{{ $item->warranty_notes }}</p>
                            @endif
                        </div>
                        @php $ws = $item->warrantyStatus(); @endphp
                        @if($ws === 'expired')
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-rose-100 text-rose-700 shrink-0">Expired</span>
                        @elseif($ws === 'expiring')
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-rose-100 text-rose-700 shrink-0">Expiring soon</span>
                        @elseif($ws === 'expiring-soon')
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-amber-100 text-amber-700 shrink-0">Expiring</span>
                        @elseif($ws === 'active')
                            <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-medium bg-emerald-100 text-emerald-700 shrink-0">Active</span>
                        @endif
                    </div>
                </x-bento-card>
            </div>
            @endif

            <x-bento-card variant="hero" class="mb-4">
                <p class="text-xs uppercase tracking-wide opacity-80 font-medium">30-day movement</p>
                <div class="mt-3 h-20">
                    <x-sparkline :data="$movement30" color="#ffffff"/>
                </div>
            </x-bento-card>

        </div>{{-- /overview --}}

        {{-- ── History Tab ── --}}
        <div x-show="tab === 'history'">
            <x-bento-card :padded="false">
                <div class="px-6 py-4 border-b border-surface-border">
                    <h2 class="text-sm font-semibold text-ink-heading">Transaction History</h2>
                </div>
                @if($transactions->isEmpty())
                    <x-empty-state icon="document-text" title="No transactions yet" hint="Receipts and releases will appear here."/>
                @else
                    <x-table :headers="['Type','Qty','From / To','Office','Date','Status','']">
                        @foreach($transactions as $tx)
                            <x-table.row>
                                <td class="px-6 py-3">
                                    @if($tx->type === 'received')
                                        <x-status-badge status="received">IN</x-status-badge>
                                    @else
                                        <x-status-badge status="released">OUT</x-status-badge>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->qty }} {{ $tx->unit }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->received_from : $tx->receiver_name }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? 'S&P Office' : $tx->released_to_office }}</td>
                                <td class="px-6 py-3 text-ink-body">{{ $tx->type === 'received' ? $tx->date_received : $tx->date_released }}</td>
                                <td class="px-6 py-3">
                                    @if($tx->type === 'received')
                                        <x-status-badge status="received"/>
                                    @elseif($tx->acknowledgment_status === 'acknowledged')
                                        <x-status-badge status="acknowledged"/>
                                    @else
                                        <x-status-badge status="pending"/>
                                    @endif
                                </td>
                                <td class="px-6 py-3 text-right">
                                    <a href="{{ route('transactions.show', $tx->id) }}" class="text-primary-600 hover:text-primary-700 text-xs font-medium">View →</a>
                                </td>
                            </x-table.row>
                        @endforeach
                    </x-table>
                @endif
            </x-bento-card>
        </div>{{-- /history --}}

        {{-- ── Audit Log Tab ── --}}
        <div x-show="tab === 'audit-log'">
            <x-bento-card :padded="false">
                <div class="px-6 py-4 border-b border-surface-border">
                    <h2 class="text-sm font-semibold text-ink-heading">Audit Log</h2>
                </div>
                @if($logs->isEmpty())
                    <x-empty-state icon="clipboard-document-list" title="No audit entries yet" hint="Quantity changes will appear here once inventory is updated."/>
                @else
                    <div class="divide-y divide-surface-border">
                        @foreach($logs as $log)
                            @php
                                $isPositive = $log->qty_change > 0;
                                $isZero     = $log->qty_change === 0;
                                $actionIcon = match($log->action) {
                                    'approved_receive' => '✅',
                                    'approved_release' => '📤',
                                    'cancelled'        => '🚫',
                                    'rejected'         => '❌',
                                    default            => '📋',
                                };
                                $changeLabel = $isZero
                                    ? '±0 ' . $item->unit
                                    : ($isPositive ? '+' : '') . $log->qty_change . ' ' . $item->unit;
                            @endphp
                            <div class="px-6 py-3 flex items-center gap-4 text-sm">
                                <span class="text-base w-5 shrink-0">{{ $actionIcon }}</span>
                                <span class="font-medium text-ink-heading w-36 shrink-0">{{ $log->action }}</span>
                                <span class="{{ $isPositive ? 'text-emerald-600' : ($isZero ? 'text-ink-muted' : 'text-rose-600') }} font-medium w-20 shrink-0">
                                    {{ $changeLabel }}
                                </span>
                                <span class="text-ink-muted w-32 shrink-0">
                                    {{ $log->qty_before }} → {{ $log->qty_after }}
                                </span>
                                <span class="text-ink-muted shrink-0">
                                    {{ $log->created_at?->format('M d, Y') ?? '—' }}
                                </span>
                                <span class="text-ink-muted shrink-0">
                                    {{ $log->user?->name ?? '—' }}
                                </span>
                                @if($log->note)
                                    <span class="text-ink-muted text-xs truncate">{{ $log->note }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-bento-card>
        </div>{{-- /audit-log --}}

    </div>{{-- /tab wrapper --}}
</x-app-layout>
```

- [ ] **Step 4: Run all items-show-related tests**

```bash
php artisan test --filter="ItemsShowTabsTest|WarrantyTest|AuditLogTest"
```

Expected: all pass. `ItemsShowTabsTest` is now green because "Overview" appears in the tab nav. The warranty and audit log tests still pass because all content is still rendered in the HTML (just wrapped in `x-show` divs).

- [ ] **Step 5: Run the full test suite**

```bash
php artisan test
```

Expected: all tests pass. Final count should be 176 + 5 (DashboardAlertsTabTest) + 5 (SidebarGroupsTest) + 4 (ItemsShowTabsTest) = **190 tests**.

If any test fails, check:
- Expiry/Low Stock/Warranty dashboard tests still referencing old card-header strings → update those strings (Step 4 of UX1 covers the known ones)
- Any test referencing the old flat sidebar loop structure → check `SidebarGroupsTest` failures

- [ ] **Step 6: Commit**

```bash
git add resources/views/items-show.blade.php \
        tests/Feature/ItemsShowTabsTest.php
git commit -m "feat: tabbed layout on item show page — Overview / History / Audit Log (UX3)"
```

---

## Self-Review

### Spec coverage
- ✅ Dashboard unified Alerts card with Alpine.js tabs (UX1)
- ✅ Tab badges show count, tabs hidden when count = 0, entire card hidden when all counts = 0 (UX1)
- ✅ Sidebar grouped sections: INVENTORY, APPROVALS, REQUISITIONS, OPERATIONS, FINANCE, ADMIN (UX2)
- ✅ APPROVALS combined badge on section header (UX2)
- ✅ localStorage state per section key (UX2)
- ✅ Icon-only collapsed mode: section headers become divider lines, nav items show icons (UX2)
- ✅ Item show page three tabs with URL hash navigation (UX3)
- ✅ Overview = meta + warranty + expiry + sparkline (UX3)
- ✅ History = transactions table (UX3)
- ✅ Audit Log = item_logs timeline (UX3)

### Breaking test updates
All known breaking assertions are covered in UX1 Step 4:
- `ExpiryTest`: `'Expiry Alerts'` → `'Expiry ('`
- `LowStockTest`: `'Low Stock Alerts'` → `'Low Stock ('`
- `WarrantyTest`: `'Warranty Alerts'` → `'Warranty ('`

### Type consistency
- `sInventory`, `sApprovals`, etc. — used consistently in x-data init and toggle handlers
- `$combinedApprovalBadge` — computed in @php block, used in section header
- Tab values `'overview'`, `'history'`, `'audit-log'` — match between `@click`, `:class`, and `x-show` expressions throughout

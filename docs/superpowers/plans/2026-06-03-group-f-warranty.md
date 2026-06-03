# Group F — Warranty Tracking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add warranty tracking to inventory items — four new fields on the `items` table, a `warrantyStatus()` helper on the Item model, a collapsible warranty section on the receive form, a warranty card on the item show page, and a warranty-expiring alerts card on the dashboard.

**Architecture:** F1 adds the migration + model helper. F2 wires the receive form so new items capture warranty info. F3 adds the item show card (only rendered when any warranty field is populated). F4 adds a dashboard query and alert card for items whose warranty expires within 90 days. No new controllers or routes are needed — all changes are to existing files.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, Alpine.js, SQLite in-memory (tests), MySQL (production).

---

## File Map

| Action | File | Purpose |
|--------|------|---------|
| Create | `database/migrations/2026_06_03_XXXXXX_add_warranty_fields_to_items.php` | 4 nullable columns on `items` |
| Modify | `app/Models/Item.php` | Add warranty fields to `$fillable`, `warranty_expiry_date` cast, `warrantyStatus()` helper |
| Modify | `app/Http/Controllers/ReceiveController.php` | Save warranty fields in both `Item::create()` calls (auto-approved + staff paths) |
| Modify | `resources/views/receive.blade.php` | Collapsible "Warranty Information" section |
| Modify | `app/Http/Controllers/DashboardController.php` | Add `$warrantyItems` query |
| Modify | `resources/views/dashboard.blade.php` | Warranty alerts card |
| Modify | `resources/views/items-show.blade.php` | Warranty bento card (shown only when any field is set) |
| Create | `tests/Feature/WarrantyTest.php` | All F tests |

---

## Task F1: Migration + Model

**Files:**
- Create: `database/migrations/2026_06_03_XXXXXX_add_warranty_fields_to_items.php`
- Modify: `app/Models/Item.php`
- Create: `tests/Feature/WarrantyTest.php`

- [ ] **Step 1: Write the failing model tests**

Create `tests/Feature/WarrantyTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyTest extends TestCase
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

    private function makeItem(Department $dept, array $overrides = []): Item
    {
        return Item::create(array_merge([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => 10,
            'current_qty'        => 10,
            'department_id'      => $dept->id,
        ], $overrides));
    }

    /** @test */
    public function test_warranty_status_returns_null_when_no_expiry_date(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept); // no warranty_expiry_date
        $this->assertNull($item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_expired_for_past_date(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->subDay()->toDateString(),
        ]);
        $this->assertEquals('expired', $item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_expiring_within_30_days(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(15)->toDateString(),
        ]);
        $this->assertEquals('expiring', $item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_expiring_soon_within_90_days(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(60)->toDateString(),
        ]);
        $this->assertEquals('expiring-soon', $item->warrantyStatus());
    }

    /** @test */
    public function test_warranty_status_returns_active_for_over_90_days(): void
    {
        $dept = $this->makeDept();
        $item = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(91)->toDateString(),
        ]);
        $this->assertEquals('active', $item->warrantyStatus());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
php artisan test tests/Feature/WarrantyTest.php --no-coverage
```

Expected: `FAIL` — column `warranty_expiry_date` doesn't exist.

- [ ] **Step 3: Create the migration**

```bash
php artisan make:migration add_warranty_fields_to_items
```

Open the generated file (timestamp prefix in `database/migrations/`). Replace its contents entirely:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->date('warranty_expiry_date')->nullable()->after('expiry_date');
            $table->string('warranty_provider', 255)->nullable()->after('warranty_expiry_date');
            $table->string('warranty_reference_no', 100)->nullable()->after('warranty_provider');
            $table->text('warranty_notes')->nullable()->after('warranty_reference_no');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'warranty_expiry_date',
                'warranty_provider',
                'warranty_reference_no',
                'warranty_notes',
            ]);
        });
    }
};
```

- [ ] **Step 4: Run the migration**

```bash
php artisan migrate
```

Expected: `items` table gains 4 new nullable columns.

- [ ] **Step 5: Add warranty fields to Item model**

Open `app/Models/Item.php`. The current `$fillable` is:

```php
protected $fillable = [
    'name',
    'category',
    'brand',
    'model_number',
    'serial_number',
    'unit',
    'total_qty_received',
    'current_qty',
    'created_by',
    'department_id',
    'expiry_date',
    'min_stock_qty',
];
```

Replace with:

```php
protected $fillable = [
    'name',
    'category',
    'brand',
    'model_number',
    'serial_number',
    'unit',
    'total_qty_received',
    'current_qty',
    'created_by',
    'department_id',
    'expiry_date',
    'min_stock_qty',
    'warranty_expiry_date',
    'warranty_provider',
    'warranty_reference_no',
    'warranty_notes',
];
```

In the same file, find the `casts()` method:

```php
protected function casts(): array
{
    return [
        'expiry_date'        => 'date',
        'current_qty'        => 'integer',
        'min_stock_qty'      => 'integer',
        'total_qty_received' => 'integer',
    ];
}
```

Replace with:

```php
protected function casts(): array
{
    return [
        'expiry_date'          => 'date',
        'warranty_expiry_date' => 'date',
        'current_qty'          => 'integer',
        'min_stock_qty'        => 'integer',
        'total_qty_received'   => 'integer',
    ];
}
```

- [ ] **Step 6: Add `warrantyStatus()` helper to Item model**

In `app/Models/Item.php`, after the `expiryStatus()` method, add:

```php
/**
 * Warranty status badge: 'expired' | 'expiring' | 'expiring-soon' | 'active' | null
 *
 * expired       = past expiry
 * expiring      = within 30 days  (red)
 * expiring-soon = 31–90 days      (amber)
 * active        = more than 90 days (green)
 */
public function warrantyStatus(): ?string
{
    if (! $this->warranty_expiry_date) return null;
    $days = now()->diffInDays($this->warranty_expiry_date, false);
    if ($days < 0)   return 'expired';
    if ($days <= 30) return 'expiring';
    if ($days <= 90) return 'expiring-soon';
    return 'active';
}
```

- [ ] **Step 7: Run the model tests**

```bash
php artisan test tests/Feature/WarrantyTest.php --no-coverage
```

Expected: all 5 tests pass.

- [ ] **Step 8: Run full suite**

```bash
php artisan test --no-coverage
```

Expected: all 164 tests pass (no regressions from migration/model change).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/*add_warranty_fields_to_items* \
        app/Models/Item.php \
        tests/Feature/WarrantyTest.php
git commit -m "feat: warranty fields migration + warrantyStatus() helper on Item (F1)"
```

---

## Task F2: Receive Form Warranty Section

**Files:**
- Modify: `app/Http/Controllers/ReceiveController.php`
- Modify: `resources/views/receive.blade.php`

- [ ] **Step 1: Write the failing test**

Open `tests/Feature/WarrantyTest.php`. Append this test before the closing `}`:

```php
    /** @test */
    public function test_receive_store_saves_warranty_fields_for_new_item(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create([
            'role'          => 'admin',
            'department_id' => $dept->id,
        ]);

        $this->actingAs($admin)
            ->post(route('receive.store'), [
                'name'                  => 'HP Laptop',
                'qty'                   => 1,
                'unit'                  => 'unit',
                'date_received'         => now()->toDateString(),
                'warranty_provider'     => 'HP Philippines',
                'warranty_reference_no' => 'WR-2024-001',
                'warranty_expiry_date'  => now()->addYears(2)->toDateString(),
                'warranty_notes'        => 'Parts and labor',
            ])
            ->assertRedirect();

        $item = Item::where('name', 'HP Laptop')->first();
        $this->assertNotNull($item);
        $this->assertEquals('HP Philippines', $item->warranty_provider);
        $this->assertEquals('WR-2024-001', $item->warranty_reference_no);
        $this->assertNotNull($item->warranty_expiry_date);
        $this->assertEquals('Parts and labor', $item->warranty_notes);
    }
```

- [ ] **Step 2: Run test to verify it fails**

```bash
php artisan test tests/Feature/WarrantyTest.php::WarrantyTest::test_receive_store_saves_warranty_fields_for_new_item --no-coverage
```

Expected: FAIL — warranty fields are null on the item (controller doesn't pass them yet).

- [ ] **Step 3: Update ReceiveController to save warranty fields**

Open `app/Http/Controllers/ReceiveController.php`. Find the **auto-approved new item** `Item::create([...])` block (inside `if ($autoApproved) { ... else { ... } }`):

```php
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
                $qtyBefore = 0;
            }
```

Replace with:

```php
            } else {
                $item = Item::create([
                    'name'                  => $request->name,
                    'category'              => $request->category,
                    'brand'                 => $request->brand,
                    'model_number'          => $request->model_number,
                    'serial_number'         => $request->serial_number,
                    'unit'                  => $request->unit ?? 'pcs',
                    'total_qty_received'    => $request->qty,
                    'current_qty'           => $request->qty,
                    'created_by'            => auth()->id(),
                    'department_id'         => $deptId,
                    'expiry_date'           => $request->expiry_date ?? null,
                    'warranty_expiry_date'  => $request->warranty_expiry_date ?? null,
                    'warranty_provider'     => $request->warranty_provider ?? null,
                    'warranty_reference_no' => $request->warranty_reference_no ?? null,
                    'warranty_notes'        => $request->warranty_notes ?? null,
                ]);
                $qtyBefore = 0;
            }
```

Now find the **staff path** `Item::create([...])` block (later in the method, inside `if (! $item) {`):

```php
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
```

Replace with:

```php
        if (! $item) {
            $item = Item::create([
                'name'                  => $request->name,
                'category'              => $request->category,
                'brand'                 => $request->brand,
                'model_number'          => $request->model_number,
                'serial_number'         => $request->serial_number,
                'unit'                  => $request->unit ?? 'pcs',
                'total_qty_received'    => 0,
                'current_qty'           => 0,
                'created_by'            => auth()->id(),
                'department_id'         => $deptId,
                'expiry_date'           => $request->expiry_date ?? null,
                'warranty_expiry_date'  => $request->warranty_expiry_date ?? null,
                'warranty_provider'     => $request->warranty_provider ?? null,
                'warranty_reference_no' => $request->warranty_reference_no ?? null,
                'warranty_notes'        => $request->warranty_notes ?? null,
            ]);
        }
```

- [ ] **Step 4: Add the warranty section to the receive form**

Open `resources/views/receive.blade.php`. Find the submit button block at the bottom of the form:

```blade
            <div class="flex gap-3">
                <x-button type="submit" variant="primary">
                    <x-heroicon-o-arrow-down-tray class="w-4 h-4"/>
                    Record Receipt
                </x-button>
                <x-button as="a" variant="ghost" href="{{ route('dashboard') }}">Cancel</x-button>
            </div>
```

Insert the warranty section **immediately before** that block:

```blade
            {{-- Warranty Information (collapsible) --}}
            <div x-data="{ open: false }" class="mb-6">
                <button type="button" @click="open = !open"
                        class="flex items-center gap-2 text-sm font-semibold text-ink-heading mb-2">
                    <x-heroicon-o-shield-check class="w-4 h-4 text-ink-muted"/>
                    Warranty Information
                    <x-heroicon-o-chevron-down class="w-4 h-4 text-ink-muted transition-transform duration-200"
                                               :style="open ? 'transform:rotate(180deg)' : ''"/>
                    <span class="text-xs text-ink-muted font-normal">(optional)</span>
                </button>
                <div x-show="open" style="display:none">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <x-label for="warranty_provider">Warranty Provider</x-label>
                            <x-input id="warranty_provider" name="warranty_provider"
                                     :value="old('warranty_provider')"
                                     placeholder="e.g. Samsung Philippines"/>
                        </div>
                        <div>
                            <x-label for="warranty_reference_no">Warranty Reference No.</x-label>
                            <x-input id="warranty_reference_no" name="warranty_reference_no"
                                     :value="old('warranty_reference_no')"
                                     placeholder="e.g. WR-2024-001234"/>
                        </div>
                        <div>
                            <x-label for="warranty_expiry_date">Warranty Expiry Date</x-label>
                            <x-input id="warranty_expiry_date" name="warranty_expiry_date"
                                     type="date" :value="old('warranty_expiry_date')"/>
                        </div>
                        <div class="md:col-span-2">
                            <x-label for="warranty_notes">Coverage Notes</x-label>
                            <x-textarea id="warranty_notes" name="warranty_notes">{{ old('warranty_notes') }}</x-textarea>
                        </div>
                    </div>
                </div>
            </div>

```

- [ ] **Step 5: Run the warranty receive test**

```bash
php artisan test tests/Feature/WarrantyTest.php --no-coverage
```

Expected: all 6 tests pass.

- [ ] **Step 6: Run full suite**

```bash
php artisan test --no-coverage
```

Expected: all 164 tests pass (no regressions).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ReceiveController.php \
        resources/views/receive.blade.php \
        tests/Feature/WarrantyTest.php
git commit -m "feat: warranty fields on receive form and ReceiveController (F2)"
```

---

## Task F3: Item Show — Warranty Card

**Files:**
- Modify: `resources/views/items-show.blade.php`

- [ ] **Step 1: Write the failing tests**

Open `tests/Feature/WarrantyTest.php`. Append these two tests before the closing `}`:

```php
    /** @test */
    public function test_items_show_displays_warranty_card_when_data_is_present(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept, [
            'warranty_provider'    => 'HP Philippines',
            'warranty_expiry_date' => now()->addYears(2)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Warranty')
            ->assertSee('HP Philippines');
    }

    /** @test */
    public function test_items_show_hides_warranty_card_when_no_warranty_data(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept); // no warranty fields

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertDontSee('Warranty Provider');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/WarrantyTest.php --no-coverage
```

Expected: `test_items_show_displays_warranty_card_when_data_is_present` fails — "HP Philippines" not found.

- [ ] **Step 3: Add warranty card to items-show.blade.php**

Open `resources/views/items-show.blade.php`. Find the existing expiry date block (the `@if($item->expiry_date)` card):

```blade
    @if($item->expiry_date)
    <div class="mb-4">
        <x-bento-card>
            ...expiry card content...
        </x-bento-card>
    </div>
    @endif
```

Add the warranty card **immediately after** that `@endif`:

```blade
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
                        <p class="text-sm text-ink-muted">Provider</p>
                        <p class="font-medium text-ink-heading mb-2">{{ $item->warranty_provider }}</p>
                    @endif
                    @if($item->warranty_reference_no)
                        <p class="text-sm text-ink-muted">Warranty Provider</p>
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
```

- [ ] **Step 4: Run all warranty tests**

```bash
php artisan test tests/Feature/WarrantyTest.php --no-coverage
```

Expected: all 8 tests pass.

- [ ] **Step 5: Run full suite**

```bash
php artisan test --no-coverage
```

Expected: all 164 tests pass.

- [ ] **Step 6: Commit**

```bash
git add resources/views/items-show.blade.php \
        tests/Feature/WarrantyTest.php
git commit -m "feat: warranty card on item show page (F3)"
```

---

## Task F4: Dashboard — Warranty Alerts

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 1: Write the failing tests**

Open `tests/Feature/WarrantyTest.php`. Append these two tests before the closing `}`:

```php
    /** @test */
    public function test_dashboard_shows_warranty_expiring_items(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        $item  = $this->makeItem($dept, [
            'warranty_expiry_date' => now()->addDays(30)->toDateString(), // within 90 days
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($item->name)
            ->assertSee('Warranty Alerts');
    }

    /** @test */
    public function test_dashboard_hides_warranty_alerts_when_no_expiring_items(): void
    {
        $dept  = $this->makeDept();
        $admin = User::factory()->create(['role' => 'admin']);
        // Item with no warranty date — should NOT appear
        $this->makeItem($dept);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Warranty Alerts');
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/WarrantyTest.php --no-coverage
```

Expected: `test_dashboard_shows_warranty_expiring_items` fails — "Warranty Alerts" not in page.

- [ ] **Step 3: Add `$warrantyItems` query to DashboardController**

Open `app/Http/Controllers/DashboardController.php`. Find the `$lowStockItems` query (near the bottom of `index()`):

```php
        $lowStockItems = Item::where('min_stock_qty', '>', 0)
            ->whereColumn('current_qty', '<=', 'min_stock_qty')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->orderBy('current_qty')
            ->limit(8)
            ->get();
```

Add `$warrantyItems` immediately after it:

```php
        $warrantyItems = Item::whereNotNull('warranty_expiry_date')
            ->where('warranty_expiry_date', '<=', Carbon::today()->addDays(90))
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->orderBy('warranty_expiry_date')
            ->limit(8)
            ->get();
```

Then add `'warrantyItems'` to the `compact(...)` call (after `'lowStockItems'`):

```php
        return view('dashboard', compact(
            'totalInStock',
            'totalReleased',
            'pendingAck',
            'acknowledged',
            'pendingTransactions',
            'weeklyActivity',
            'topOffice',
            'topItem',
            'pcThisMonth',
            'pcVouchersThisMonth',
            'pcPendingAck',
            'pcPendingSettle',
            'recentVouchers',
            'expiringItems',
            'lowStockItems',
            'warrantyItems',
            'pendingApprovalCount',
            'myPendingCount',
        ));
```

- [ ] **Step 4: Add Warranty Alerts card to dashboard.blade.php**

Open `resources/views/dashboard.blade.php`. Find the `{{-- Low Stock Alert --}}` block and its closing `@endif`. Add the warranty card **immediately after** that `@endif`:

```blade
    {{-- Warranty Alerts --}}
    @if($warrantyItems->isNotEmpty())
    <x-bento-card :padded="false" class="mb-4">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-heroicon-o-shield-exclamation class="w-4 h-4 text-amber-500"/>
                <h2 class="text-sm font-semibold text-ink-heading">Warranty Alerts</h2>
            </div>
            <a href="{{ route('items.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all items</a>
        </div>
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
    </x-bento-card>
    @endif
```

- [ ] **Step 5: Run all warranty tests**

```bash
php artisan test tests/Feature/WarrantyTest.php --no-coverage
```

Expected: all 10 tests pass.

- [ ] **Step 6: Run full suite**

```bash
php artisan test --no-coverage
```

Expected: all 164 tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DashboardController.php \
        resources/views/dashboard.blade.php \
        tests/Feature/WarrantyTest.php
git commit -m "feat: warranty expiring alerts on dashboard (F4)"
```

---

## Self-Review

### Spec coverage

| Spec requirement | Task |
|-----------------|------|
| `warranty_expiry_date` date nullable on items | F1 — migration |
| `warranty_provider` string(255) nullable on items | F1 — migration |
| `warranty_reference_no` string(100) nullable on items | F1 — migration |
| `warranty_notes` text nullable on items | F1 — migration |
| Add 4 fields to `Item::$fillable` | F1 — model |
| `warrantyStatus()` returns null when no date | F1 — helper + test |
| `warrantyStatus()` returns 'expired' for past date | F1 — helper + test |
| `warrantyStatus()` returns 'expiring' within 30 days (red) | F1 — helper + test |
| `warrantyStatus()` returns 'expiring-soon' within 90 days (amber) | F1 — helper + test |
| `warrantyStatus()` returns 'active' for >90 days (green) | F1 — helper + test |
| Receive form: collapsible "Warranty Information" section | F2 — receive.blade.php |
| Receive form fields: provider, reference no., expiry date, notes | F2 — receive.blade.php |
| ReceiveController saves warranty fields for new items (auto-approved path) | F2 — ReceiveController |
| ReceiveController saves warranty fields for new items (staff path) | F2 — ReceiveController |
| Item show: warranty card (only when any field populated) | F3 — items-show.blade.php |
| Item show: warranty card shows provider, reference, expiry, coverage | F3 — items-show.blade.php |
| Item show: status badge (Expired/Expiring soon/Expiring/Active) | F3 — items-show.blade.php |
| Dashboard: warranty items expiring within 90 days | F4 — DashboardController |
| Dashboard: warranty alerts card with item name, provider, expiry, status | F4 — dashboard.blade.php |
| Dashboard: card hidden if no expiring warranty items | F4 — `@if($warrantyItems->isNotEmpty())` |
| Dashboard: department scope applied to warranty query | F4 — `->when($scope, ...)` |

### Placeholder scan
No TBD or TODO sections. All code is complete. ✓

### Type consistency
- `warrantyStatus()` defined in F1, called in F3 (items-show) and F4 (dashboard) — same method name throughout ✓
- `warranty_expiry_date` field name consistent across migration, model `$fillable`, casts, controller, view ✓
- `$warrantyItems` passed from DashboardController, used as `$warrantyItems` in dashboard view ✓
- `Item::create([...warranty fields...])` uses the same key names as `$fillable` ✓

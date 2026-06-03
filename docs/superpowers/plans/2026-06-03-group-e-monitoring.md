# Group E — Monitoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add low-stock alerts to the dashboard and item pages, and an immutable audit log that records every inventory quantity change with who made it, when, and by how much.

**Architecture:** E1 wires the existing `Item::isBelowMinStock()` helper into the dashboard and item show page — no new model. E2–E4 build the `item_logs` table: a write-only log populated by four controllers (ReceiveController, ReleaseController, TransactionApprovalController, TransactionCancelController) via a static `ItemLog::record()` helper, displayed as a timeline below the transaction history on the item show page.

**Tech Stack:** Laravel 13, PHP 8.3, Blade, Alpine.js, SQLite in-memory (tests), MySQL (production).

---

## File Map

| Action | File | Purpose |
|--------|------|---------|
| Modify | `app/Http/Controllers/DashboardController.php` | Add `$lowStockItems` query |
| Modify | `resources/views/dashboard.blade.php` | Add Low Stock alert card (after Expiry Alerts) |
| Modify | `resources/views/items-show.blade.php` | Add Low Stock / Out of Stock badge + audit log timeline section |
| Create | `database/migrations/2026_06_03_000001_create_item_logs_table.php` | `item_logs` table — immutable log rows |
| Create | `app/Models/ItemLog.php` | `ItemLog` model with `record()` static helper |
| Modify | `app/Models/Item.php` | Add `logs()` HasMany relationship |
| Modify | `app/Http/Controllers/ReceiveController.php` | Write `approved_receive` log entry in auto-approved path |
| Modify | `app/Http/Controllers/ReleaseController.php` | Write `approved_release` log entry in auto-approved path |
| Modify | `app/Http/Controllers/TransactionApprovalController.php` | Write log entries in `approve()` and `bulkApprove()` |
| Modify | `app/Http/Controllers/TransactionCancelController.php` | Write `cancelled` log entry in `cancel()` |
| Modify | `app/Http/Controllers/ItemController.php` | Load `$logs` in `show()` |
| Create | `tests/Feature/LowStockTest.php` | Feature tests for E1 |
| Create | `tests/Feature/AuditLogTest.php` | Feature tests for E2–E4 |

---

## Task E1: Low Stock Alerts

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php`
- Modify: `resources/views/items-show.blade.php`
- Create: `tests/Feature/LowStockTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/LowStockTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LowStockTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D'  . self::$seq,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeStaff(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);
    }

    private function makeItem(Department $dept, int $qty, int $minQty): Item
    {
        return Item::create([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'min_stock_qty'      => $minQty,
            'department_id'      => $dept->id,
        ]);
    }

    /** @test */
    public function test_dashboard_shows_low_stock_items(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 2, minQty: 10); // 2 <= 10 → low stock

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($item->name)
            ->assertSee('Low Stock');
    }

    /** @test */
    public function test_dashboard_does_not_show_item_with_zero_min_stock(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $this->makeItem($dept, qty: 0, minQty: 0); // min_stock_qty = 0 → not a low stock alert

        $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();
        $response->assertDontSee('Low Stock Alerts');
    }

    /** @test */
    public function test_dashboard_low_stock_is_scoped_to_staff_dept(): void
    {
        $dept1 = $this->makeDept();
        $dept2 = $this->makeDept();
        $staff = $this->makeStaff($dept1);
        $itemOwn   = $this->makeItem($dept1, qty: 1, minQty: 5);  // own dept — should show
        $itemOther = $this->makeItem($dept2, qty: 1, minQty: 5);  // other dept — should NOT show

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee($itemOwn->name)
            ->assertDontSee($itemOther->name);
    }

    /** @test */
    public function test_items_show_displays_low_stock_badge(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, qty: 3, minQty: 10);

        $this->actingAs($staff)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Low Stock');
    }

    /** @test */
    public function test_items_show_displays_out_of_stock_badge(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, qty: 0, minQty: 0);

        $this->actingAs($staff)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Out of stock');
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
php artisan test tests/Feature/LowStockTest.php --no-coverage
```

Expected: `test_dashboard_shows_low_stock_items` and `test_dashboard_low_stock_is_scoped_to_staff_dept` fail — `$lowStockItems` undefined in view.

- [ ] **Step 3: Add `$lowStockItems` to DashboardController**

Open `app/Http/Controllers/DashboardController.php`. Find the existing `$expiringItems` query (near the bottom of `index()`):

```php
        $expiringItems = Item::whereNotNull('expiry_date')
            ->where('expiry_date', '<=', Carbon::today()->addDays(30))
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->orderBy('expiry_date')
            ->limit(8)
            ->get();
```

Add `$lowStockItems` immediately after it:

```php
        $lowStockItems = Item::where('min_stock_qty', '>', 0)
            ->whereColumn('current_qty', '<=', 'min_stock_qty')
            ->when($scope, fn($q) => $q->where('department_id', $scope))
            ->orderBy('current_qty')
            ->limit(8)
            ->get();
```

Then add `'lowStockItems'` to the `compact(...)` call at the end of the method (after `'expiringItems'`):

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
            'pendingApprovalCount',
            'myPendingCount',
        ));
```

- [ ] **Step 4: Add Low Stock alert card to dashboard.blade.php**

Open `resources/views/dashboard.blade.php`. Find the Expiry Alert block:

```blade
    {{-- Expiry Alert --}}
    @if($expiringItems->isNotEmpty())
```

Add the Low Stock card **immediately after** the closing `@endif` of the Expiry Alert block:

```blade
    {{-- Low Stock Alert --}}
    @if($lowStockItems->isNotEmpty())
    <x-bento-card :padded="false" class="mb-4">
        <div class="px-6 py-4 border-b border-surface-border flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-heroicon-o-arrow-trending-down class="w-4 h-4 text-amber-500"/>
                <h2 class="text-sm font-semibold text-ink-heading">Low Stock Alerts</h2>
            </div>
            <a href="{{ route('items.index') }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">View all items</a>
        </div>
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
    </x-bento-card>
    @endif
```

- [ ] **Step 5: Add Low Stock / Out of Stock badge to items-show.blade.php**

Open `resources/views/items-show.blade.php`. Find the `<x-slot:actions>` in the page header:

```blade
        <x-slot:actions>
            @if($item->current_qty > 0)
                <x-status-badge status="acknowledged">{{ $item->current_qty }} {{ $item->unit }} in stock</x-status-badge>
            @else
                <x-status-badge status="pending">Out of stock</x-status-badge>
            @endif
        </x-slot:actions>
```

Replace with:

```blade
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
```

- [ ] **Step 6: Run all tests**

```bash
php artisan test --no-coverage
```

Expected: all 155 tests pass (150 before + 5 new LowStockTest tests).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/DashboardController.php \
        resources/views/dashboard.blade.php \
        resources/views/items-show.blade.php \
        tests/Feature/LowStockTest.php
git commit -m "feat: low stock alerts on dashboard and item page badge (E1)"
```

---

## Task E2: Audit Log — Migration + Model

**Files:**
- Create: `database/migrations/2026_06_03_000001_create_item_logs_table.php`
- Create: `app/Models/ItemLog.php`
- Modify: `app/Models/Item.php`

- [ ] **Step 1: Create the migration**

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
php artisan make:migration create_item_logs_table
```

Open the generated file (it will be in `database/migrations/` with a timestamp prefix). Replace its contents entirely:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('action', [
                'received',
                'released',
                'approved_receive',
                'approved_release',
                'rejected',
                'cancelled',
            ]);
            $table->integer('qty_change');   // positive = added, negative = deducted, 0 = no change
            $table->integer('qty_before');
            $table->integer('qty_after');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — logs are immutable
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_logs');
    }
};
```

- [ ] **Step 2: Run the migration**

```bash
php artisan migrate
```

Expected: `item_logs` table created.

- [ ] **Step 3: Create the ItemLog model**

Create `app/Models/ItemLog.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemLog extends Model
{
    /**
     * Logs are immutable — only created_at, no updated_at.
     * The DB sets created_at via useCurrent() default.
     */
    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'user_id',
        'action',
        'qty_change',
        'qty_before',
        'qty_after',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Write an audit log entry for an item quantity change.
     *
     * @param  Item    $item       The item whose stock changed
     * @param  string  $action     One of the enum values
     * @param  int     $qtyChange  Positive = added, negative = deducted, 0 = no change
     * @param  int     $qtyBefore  Stock level BEFORE the change
     * @param  string|null $note   Optional context (e.g. "Transaction #42")
     */
    public static function record(
        Item $item,
        string $action,
        int $qtyChange,
        int $qtyBefore,
        ?string $note = null
    ): void {
        static::create([
            'item_id'    => $item->id,
            'user_id'    => auth()->id(),
            'action'     => $action,
            'qty_change' => $qtyChange,
            'qty_before' => $qtyBefore,
            'qty_after'  => $qtyBefore + $qtyChange,
            'note'       => $note,
        ]);
    }
}
```

- [ ] **Step 4: Add `logs()` relationship to Item model**

Open `app/Models/Item.php`. After the existing `transactions()` method, add:

```php
    public function logs()
    {
        return $this->hasMany(ItemLog::class)->latest('created_at');
    }
```

Also add the import at the top if not already present (it won't be, since ItemLog is new). The `HasMany` return type is inferred; no import needed in this codebase's style.

- [ ] **Step 5: Write the model tests**

Add to `tests/Feature/AuditLogTest.php` (create this file):

```php
<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\ItemLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private static int $seq = 0;

    private function makeDept(): Department
    {
        self::$seq++;
        return Department::create([
            'name'      => 'Dept ' . self::$seq,
            'code'      => 'D'  . self::$seq,
            'is_active' => true,
        ]);
    }

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function makeStaff(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => false,
        ]);
    }

    private function makeItem(Department $dept, int $qty = 10): Item
    {
        return Item::create([
            'name'               => 'Item ' . self::$seq,
            'unit'               => 'pcs',
            'total_qty_received' => $qty,
            'current_qty'        => $qty,
            'department_id'      => $dept->id,
        ]);
    }

    /** @test */
    public function test_item_log_record_creates_row_correctly(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 100);

        $this->actingAs($admin);

        ItemLog::record($item, 'approved_receive', 5, 100, 'Transaction #1');

        $log = ItemLog::first();
        $this->assertNotNull($log);
        $this->assertEquals($item->id, $log->item_id);
        $this->assertEquals($admin->id, $log->user_id);
        $this->assertEquals('approved_receive', $log->action);
        $this->assertEquals(5, $log->qty_change);
        $this->assertEquals(100, $log->qty_before);
        $this->assertEquals(105, $log->qty_after);
        $this->assertEquals('Transaction #1', $log->note);
    }

    /** @test */
    public function test_item_logs_relationship_returns_logs_in_descending_order(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 100);

        $this->actingAs($admin);

        ItemLog::record($item, 'approved_receive', 5, 100);
        ItemLog::record($item, 'approved_release', -2, 105);

        $logs = $item->logs()->get();
        $this->assertCount(2, $logs);
        // Latest first
        $this->assertEquals('approved_release', $logs->first()->action);
    }
}
```

- [ ] **Step 6: Run the tests**

```bash
php artisan test tests/Feature/AuditLogTest.php --no-coverage
```

Expected: 2 tests pass.

- [ ] **Step 7: Run full suite**

```bash
php artisan test --no-coverage
```

Expected: all 157 tests pass.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/*create_item_logs_table* \
        app/Models/ItemLog.php \
        app/Models/Item.php \
        tests/Feature/AuditLogTest.php
git commit -m "feat: item_logs migration + ItemLog model with record() helper (E2)"
```

---

## Task E3: Audit Log — Write Entries in Controllers

**Files:**
- Modify: `app/Http/Controllers/ReceiveController.php`
- Modify: `app/Http/Controllers/ReleaseController.php`
- Modify: `app/Http/Controllers/TransactionApprovalController.php`
- Modify: `app/Http/Controllers/TransactionCancelController.php`

- [ ] **Step 1: Add audit log tests to AuditLogTest.php**

Open `tests/Feature/AuditLogTest.php` and add these helper methods and test methods to the class (append before the closing `}`):

```php
    private function makeReceiveTx(Item $item, User $submitter): \App\Models\Transaction
    {
        return \App\Models\Transaction::create([
            'type'                 => 'received',
            'item_id'              => $item->id,
            'item_name_snapshot'   => $item->name,
            'qty'                  => 3,
            'unit'                 => $item->unit,
            'received_from'        => 'Supply',
            'date_received'        => now()->toDateString(),
            'received_by_user_id'  => $submitter->id,
            'acknowledgment_status'=> 'pending',
            'head_approval_status' => 'pending',
            'department_id'        => $item->department_id,
        ]);
    }

    private function makeReleaseTx(Item $item, User $submitter): \App\Models\Transaction
    {
        return \App\Models\Transaction::create([
            'type'                  => 'released',
            'item_id'               => $item->id,
            'item_name_snapshot'    => $item->name,
            'qty'                   => 2,
            'unit'                  => $item->unit,
            'released_to_office'    => 'Nursing Unit',
            'receiver_name'         => 'Nurse',
            'date_released'         => now()->toDateString(),
            'released_by_user_id'   => $submitter->id,
            'acknowledgment_status' => 'pending',
            'head_approval_status'  => 'pending',
            'department_id'         => $item->department_id,
        ]);
    }

    private function makeHead(Department $dept): User
    {
        return User::factory()->create([
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => true,
        ]);
    }

    /** @test */
    public function test_approving_receive_writes_approved_receive_log(): void
    {
        $dept    = $this->makeDept();
        $head    = $this->makeHead($dept);
        $staff   = $this->makeStaff($dept);
        $item    = $this->makeItem($dept, qty: 10);
        $tx      = $this->makeReceiveTx($item, $staff);

        $this->actingAs($head)
            ->patch(route('approvals.approve', $tx));

        $log = ItemLog::where('item_id', $item->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('approved_receive', $log->action);
        $this->assertEquals(3, $log->qty_change);
        $this->assertEquals(10, $log->qty_before);
        $this->assertEquals(13, $log->qty_after);
    }

    /** @test */
    public function test_approving_release_writes_approved_release_log(): void
    {
        $dept    = $this->makeDept();
        $head    = $this->makeHead($dept);
        $staff   = $this->makeStaff($dept);
        $item    = $this->makeItem($dept, qty: 10);
        $tx      = $this->makeReleaseTx($item, $staff);

        $this->actingAs($head)
            ->patch(route('approvals.approve', $tx));

        $log = ItemLog::where('item_id', $item->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('approved_release', $log->action);
        $this->assertEquals(-2, $log->qty_change);
        $this->assertEquals(10, $log->qty_before);
        $this->assertEquals(8, $log->qty_after);
    }

    /** @test */
    public function test_cancelling_pending_receive_writes_cancelled_log(): void
    {
        $dept  = $this->makeDept();
        $staff = $this->makeStaff($dept);
        $item  = $this->makeItem($dept, qty: 0);
        $tx    = $this->makeReceiveTx($item, $staff);

        $this->actingAs($staff)
            ->patch(route('transactions.cancel', $tx));

        $log = ItemLog::where('item_id', $item->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('cancelled', $log->action);
        $this->assertEquals(0, $log->qty_change);
    }

    /** @test */
    public function test_auto_approve_receive_writes_log_entry(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('receive.store'), [
                'name'          => 'Test Paper',
                'qty'           => 5,
                'unit'          => 'ream',
                'date_received' => now()->toDateString(),
            ]);

        $log = ItemLog::first();
        $this->assertNotNull($log);
        $this->assertEquals('approved_receive', $log->action);
        $this->assertEquals(5, $log->qty_change);
    }

    /** @test */
    public function test_auto_approve_release_writes_log_entry(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 10);
        // Admin's department must match the item's department
        $admin->update(['department_id' => $dept->id]);

        $this->actingAs($admin)
            ->post(route('release.store'), [
                'item_id'          => $item->id,
                'qty'              => 3,
                'released_to_office' => 'Nursing Unit',
                'receiver_name'    => 'Nurse',
                'date_released'    => now()->toDateString(),
            ]);

        $log = ItemLog::where('item_id', $item->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals('approved_release', $log->action);
        $this->assertEquals(-3, $log->qty_change);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
php artisan test tests/Feature/AuditLogTest.php --no-coverage
```

Expected: the 5 new controller tests fail — "no log written" / `assertNotNull` fails.

- [ ] **Step 3: Add ItemLog to ReceiveController**

Open `app/Http/Controllers/ReceiveController.php`. Add at the top with the other `use` statements:

```php
use App\Models\ItemLog;
```

Find the auto-approved path — the block where `$item->save()` is called before `Transaction::create(...)`. Currently:

```php
        if ($autoApproved) {
            // Head / Admin: update inventory immediately
            if ($item) {
                $item->total_qty_received += $request->qty;
                $item->current_qty        += $request->qty;
                $item->save();
            } else {
                $item = Item::create([...]);
            }

            Transaction::create([...]);

            return redirect()->route('dashboard')...;
        }
```

Add the log entry after `$item->save()` (in the existing-item branch) and after `Item::create(...)` (in the new-item branch). Replace the entire `if ($autoApproved)` block with:

```php
        if ($autoApproved) {
            // Head / Admin: update inventory immediately
            if ($item) {
                $qtyBefore = $item->current_qty;
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
                $qtyBefore = 0; // new item starts at 0
            }

            ItemLog::record($item, 'approved_receive', $request->qty, $qtyBefore);

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

            return redirect()->route('dashboard')
                ->with('success', "{$request->qty} {$item->unit} of \"{$item->name}\" received and added to inventory.");
        }
```

- [ ] **Step 4: Add ItemLog to ReleaseController**

Open `app/Http/Controllers/ReleaseController.php`. Add at the top:

```php
use App\Models\ItemLog;
```

Find the auto-approved `store()` path. Currently:

```php
        if ($autoApproved) {
            // Head / Admin: decrement inventory immediately
            $item->current_qty -= $request->qty;
            $item->save();

            Transaction::create([...]);

            return redirect()->route('acknowledge.index')...;
        }
```

Replace with:

```php
        if ($autoApproved) {
            // Head / Admin: decrement inventory immediately
            $qtyBefore = $item->current_qty;
            $item->current_qty -= $request->qty;
            $item->save();

            ItemLog::record($item, 'approved_release', -$request->qty, $qtyBefore);

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
                ->with('success', "{$request->qty} {$item->unit} of \"{$item->name}\" released and deducted from inventory. Awaiting acknowledgment.");
        }
```

- [ ] **Step 5: Add ItemLog to TransactionApprovalController**

Open `app/Http/Controllers/TransactionApprovalController.php`. Add at the top:

```php
use App\Models\ItemLog;
```

In `approve()`, find the two inventory update blocks:

```php
        if ($transaction->type === 'received') {
            $item->total_qty_received += $transaction->qty;
            $item->current_qty        += $transaction->qty;
            $item->save();
        } elseif ($transaction->type === 'released') {
            if ($item->current_qty < $transaction->qty) {
                return back()->with('error', ...);
            }
            $item->current_qty -= $transaction->qty;
            $item->save();
        }
```

Replace with:

```php
        if ($transaction->type === 'received') {
            $qtyBefore = $item->current_qty;
            $item->total_qty_received += $transaction->qty;
            $item->current_qty        += $transaction->qty;
            $item->save();
            ItemLog::record($item, 'approved_receive', $transaction->qty, $qtyBefore, "Transaction #{$transaction->id}");
        } elseif ($transaction->type === 'released') {
            if ($item->current_qty < $transaction->qty) {
                return back()->with('error',
                    "Cannot approve: only {$item->current_qty} {$item->unit} available, but {$transaction->qty} requested.");
            }
            $qtyBefore = $item->current_qty;
            $item->current_qty -= $transaction->qty;
            $item->save();
            ItemLog::record($item, 'approved_release', -$transaction->qty, $qtyBefore, "Transaction #{$transaction->id}");
        }
```

In `bulkApprove()`, find the two `DB::transaction(...)` blocks inside the `foreach`. Currently:

```php
            if ($transaction->type === 'received') {
                DB::transaction(function() use ($item, $transaction, &$updates) {
                    $item->total_qty_received += $transaction->qty;
                    $item->current_qty += $transaction->qty;
                    $item->save();
                    $transaction->update([...]);
                });
            } elseif ($transaction->type === 'released') {
                if ($item->current_qty < $transaction->qty) { $failed[] = ...; continue; }
                DB::transaction(function() use ($item, $transaction) {
                    $item->current_qty -= $transaction->qty;
                    $item->save();
                    $transaction->update([...]);
                });
            }
```

Replace with:

```php
            if ($transaction->type === 'received') {
                $qtyBefore = $item->current_qty;
                DB::transaction(function() use ($item, $transaction) {
                    $item->total_qty_received += $transaction->qty;
                    $item->current_qty += $transaction->qty;
                    $item->save();
                    $transaction->update([
                        'head_approval_status' => 'approved',
                        'head_approved_by_id'  => auth()->id(),
                        'head_approved_at'      => now(),
                    ]);
                });
                ItemLog::record($item, 'approved_receive', $transaction->qty, $qtyBefore, "Transaction #{$transaction->id} (bulk)");
            } elseif ($transaction->type === 'released') {
                if ($item->current_qty < $transaction->qty) {
                    $failed[] = "\"{$transaction->item_name_snapshot}\": insufficient stock ({$item->current_qty} available, {$transaction->qty} requested)";
                    continue;
                }
                $qtyBefore = $item->current_qty;
                DB::transaction(function() use ($item, $transaction) {
                    $item->current_qty -= $transaction->qty;
                    $item->save();
                    $transaction->update([
                        'head_approval_status'  => 'approved',
                        'head_approved_by_id'   => auth()->id(),
                        'head_approved_at'       => now(),
                        'acknowledgment_status'  => 'pending',
                    ]);
                });
                ItemLog::record($item, 'approved_release', -$transaction->qty, $qtyBefore, "Transaction #{$transaction->id} (bulk)");
            }
```

**Important:** After replacing the bulkApprove DB::transaction blocks above, also verify the `$transaction->update(...)` calls inside those closures match the original code exactly (the `head_approved_by_id` and `head_approved_at` fields must be present). Read the existing bulkApprove method carefully before editing.

- [ ] **Step 6: Add ItemLog to TransactionCancelController**

Open `app/Http/Controllers/TransactionCancelController.php`. Add at the top:

```php
use App\Models\ItemLog;
```

In `cancel()`, find:

```php
        $transaction->update(['head_approval_status' => 'cancelled']);

        // Receive-only: clean up an item that was created solely by this submission.
```

Add the log entry between the update and the item-cleanup block:

```php
        $transaction->update(['head_approval_status' => 'cancelled']);

        // Write audit log entry BEFORE potential item deletion
        if ($transaction->item) {
            ItemLog::record(
                $transaction->item,
                'cancelled',
                0,
                $transaction->item->current_qty,
                "Transaction #{$transaction->id} cancelled"
            );
        }

        // Receive-only: clean up an item that was created solely by this submission.
```

- [ ] **Step 7: Run the controller audit log tests**

```bash
php artisan test tests/Feature/AuditLogTest.php --no-coverage
```

Expected: all 7 tests pass.

- [ ] **Step 8: Run full suite**

```bash
php artisan test --no-coverage
```

Expected: all tests pass.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/ReceiveController.php \
        app/Http/Controllers/ReleaseController.php \
        app/Http/Controllers/TransactionApprovalController.php \
        app/Http/Controllers/TransactionCancelController.php \
        tests/Feature/AuditLogTest.php
git commit -m "feat: write audit log entries in all inventory controllers (E3)"
```

---

## Task E4: Audit Log — Display on Item Show Page

**Files:**
- Modify: `app/Http/Controllers/ItemController.php`
- Modify: `resources/views/items-show.blade.php`

- [ ] **Step 1: Add audit log display test to AuditLogTest.php**

Open `tests/Feature/AuditLogTest.php` and append this test before the closing `}`:

```php
    /** @test */
    public function test_items_show_displays_audit_log_entries(): void
    {
        $dept  = $this->makeDept();
        $admin = $this->makeAdmin();
        $item  = $this->makeItem($dept, qty: 100);

        $this->actingAs($admin);
        ItemLog::record($item, 'approved_receive', 10, 90, 'Transaction #7');
        ItemLog::record($item, 'approved_release', -3, 100, 'Transaction #8');

        $this->actingAs($admin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('approved_receive')
            ->assertSee('approved_release')
            ->assertSee('Audit Log');
    }
```

- [ ] **Step 2: Run the new test to verify it fails**

```bash
php artisan test tests/Feature/AuditLogTest.php::AuditLogTest::test_items_show_displays_audit_log_entries --no-coverage
```

Expected: FAIL — "Audit Log" not found on items-show page.

- [ ] **Step 3: Load `$logs` in ItemController::show()**

Open `app/Http/Controllers/ItemController.php`. Add the import at the top:

```php
use App\Models\ItemLog;
```

Find the `show()` method:

```php
    public function show(Item $item)
    {
        $scope = $this->deptScope();
        if ($scope && $item->department_id !== $scope) {
            abort(403);
        }
        $transactions = $item->transactions()->latest()->get();
        $movement30 = $this->movement30($item);
        return view('items-show', compact('item', 'transactions', 'movement30'));
    }
```

Replace with:

```php
    public function show(Item $item)
    {
        $scope = $this->deptScope();
        if ($scope && $item->department_id !== $scope) {
            abort(403);
        }
        $transactions = $item->transactions()->latest()->get();
        $movement30   = $this->movement30($item);
        $logs         = $item->logs()->with('user')->get();
        return view('items-show', compact('item', 'transactions', 'movement30', 'logs'));
    }
```

- [ ] **Step 4: Add the Audit Log timeline to items-show.blade.php**

Open `resources/views/items-show.blade.php`. Find the closing `</x-app-layout>` tag at the very end. Add the audit log card **before** `</x-app-layout>`:

```blade
    {{-- Audit Log --}}
    <x-bento-card :padded="false" class="mt-4">
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
                        <span class="text-ink-muted truncate">
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
```

- [ ] **Step 5: Run all audit log tests**

```bash
php artisan test tests/Feature/AuditLogTest.php --no-coverage
```

Expected: all 8 tests pass.

- [ ] **Step 6: Run full suite**

```bash
php artisan test --no-coverage
```

Expected: all tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ItemController.php \
        resources/views/items-show.blade.php
git commit -m "feat: audit log timeline on item show page (E4)"
```

---

## Self-Review

### Spec coverage

| Spec requirement | Task |
|-----------------|------|
| Remove supply-hub restriction from low stock query | E1 — uses `$scope` (deptScope), no hard-coded dept restriction |
| Items low stock when `current_qty < min_stock_qty AND min_stock_qty > 0` | E1 — `whereColumn('current_qty', '<=', 'min_stock_qty')` + `where('min_stock_qty', '>', 0)` |
| Dashboard: Low stock items displayed | E1 — `$lowStockItems` card in dashboard |
| Item show page: amber "Low Stock" badge | E1 — `@elseif($item->isBelowMinStock())` |
| Item show page: red "Out of Stock" if qty = 0 | E1 — `@if($item->current_qty === 0)` |
| `item_logs` table with all specified columns | E2 — migration |
| `action` enum: received/released/approved_receive/approved_release/rejected/cancelled | E2 — migration |
| `qty_change`, `qty_before`, `qty_after` integer columns | E2 — migration |
| `note` string nullable | E2 — migration |
| `created_at` only (immutable, no updated_at) | E2 — `public $timestamps = false;`, DB `useCurrent()` |
| ItemLog belongs to Item and User | E2 — relationships |
| Log `approved_receive` from ReceiveController auto-approve | E3 — ReceiveController |
| Log `approved_release` from ReleaseController auto-approve | E3 — ReleaseController |
| Log `approved_receive` from TransactionApprovalController::approve() receive | E3 — approve() |
| Log `approved_release` from TransactionApprovalController::approve() release | E3 — approve() |
| Log `cancelled` from TransactionCancelController::cancel() | E3 — cancel() |
| Audit log displayed on item show page | E4 — items-show bento card |
| Timeline shows action, qty_change, before→after, date, user | E4 — log timeline rows |

### Placeholder scan
No TBD, TODO, or vague steps. All code is complete. ✓

### Type consistency
- `ItemLog::record(Item $item, string $action, int $qtyChange, int $qtyBefore, ?string $note)` — signature used consistently across E2 (definition) and E3 (all call sites) ✓
- `$item->logs()` HasMany — defined in E2, loaded in E4 with `->with('user')` ✓
- `$logs` passed to view in E4 — used as `$logs` in template ✓
- Route names unchanged — all existing tests continue to pass ✓

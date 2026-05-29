# Petty Cash, Roles & Reports Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add petty cash voucher tracking with auto-inventory integration, three user roles (Admin/Staff/Accounting), a unified Reports hub, and user management to the MIS Inventory system.

**Architecture:** Separate `petty_cash_vouchers` + `petty_cash_items` tables feed inventory via `Transaction` records on submit. A single `EnsureRole` middleware guards route groups. Reports are rendered server-side with optional CSV export — no separate package needed.

**Tech Stack:** Laravel 13, Blade components (existing design system), Alpine.js 3, Tailwind CSS 3 (existing Medical Teal palette), PHPUnit.

---

## File Map

**New migrations:**
- `database/migrations/2026_05_27_000001_add_role_is_active_to_users_table.php`
- `database/migrations/2026_05_27_000002_create_petty_cash_vouchers_table.php`
- `database/migrations/2026_05_27_000003_create_petty_cash_items_table.php`

**New models:**
- `app/Models/PettyCashVoucher.php`
- `app/Models/PettyCashItem.php`

**New controllers:**
- `app/Http/Controllers/PettyCashController.php`
- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/UserController.php`

**New middleware:**
- `app/Http/Middleware/EnsureRole.php`

**New views:**
- `resources/views/petty-cash/index.blade.php`
- `resources/views/petty-cash/create.blade.php`
- `resources/views/petty-cash/show.blade.php`
- `resources/views/petty-cash/print.blade.php`
- `resources/views/reports/index.blade.php`
- `resources/views/users/index.blade.php`
- `resources/views/users/create.blade.php`
- `resources/views/users/edit.blade.php`

**Modified files:**
- `app/Models/User.php` — add role, is_active, helper methods
- `app/Http/Controllers/DashboardController.php` — petty cash stats
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php` — block inactive users
- `resources/views/dashboard.blade.php` — new tiles + recent vouchers
- `resources/views/layouts/app.blade.php` — role-gated nav + badges
- `routes/web.php` — new route groups with role middleware
- `bootstrap/app.php` — register EnsureRole middleware alias

**New tests:**
- `tests/Feature/RoleMiddlewareTest.php`
- `tests/Feature/PettyCashVoucherTest.php`
- `tests/Feature/UserManagementTest.php`
- `tests/Feature/ReportControllerTest.php`

---

## Task 1: User roles migration

**Files:**
- Create: `database/migrations/2026_05_27_000001_add_role_is_active_to_users_table.php`

- [ ] **Step 1: Create migration**

```bash
cd /Users/bayanestonilo/Sites/mis-inventory
php artisan make:migration add_role_is_active_to_users_table
```

Rename the generated file to `2026_05_27_000001_add_role_is_active_to_users_table.php` and replace its contents:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff', 'accounting'])
                  ->default('staff')
                  ->after('password');
            $table->boolean('is_active')->default(true)->after('role');
        });

        // Promote all existing users to admin so nothing breaks
        DB::table('users')->update(['role' => 'admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active']);
        });
    }
};
```

- [ ] **Step 2: Run migration**

```bash
php artisan migrate
```

Expected: `add_role_is_active_to_users_table` runs without error.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_05_27_000001_add_role_is_active_to_users_table.php
git commit -m "feat(roles): add role and is_active columns to users"
```

---

## Task 2: Update User model + EnsureRole middleware

**Files:**
- Modify: `app/Models/User.php`
- Create: `app/Http/Middleware/EnsureRole.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Update User model**

Replace the `$fillable` array and add casts + helper methods in `app/Models/User.php`:

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'role',
    'is_active',
];

protected $casts = [
    'email_verified_at' => 'datetime',
    'password'          => 'hashed',
    'is_active'         => 'boolean',
];

public function isAdmin(): bool       { return $this->role === 'admin'; }
public function isStaff(): bool       { return $this->role === 'staff'; }
public function isAccounting(): bool  { return $this->role === 'accounting'; }
public function canAccessReports(): bool  { return in_array($this->role, ['admin', 'accounting']); }
public function canManageUsers(): bool    { return $this->role === 'admin'; }
public function canCreateVoucher(): bool  { return in_array($this->role, ['admin', 'staff']); }
public function canSettleVoucher(): bool  { return in_array($this->role, ['admin', 'accounting']); }
```

- [ ] **Step 2: Create EnsureRole middleware**

```php
<?php
// app/Http/Middleware/EnsureRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403, 'Your account is inactive. Contact an administrator.');
        }

        if (! in_array($user->role, $roles)) {
            abort(403, 'You do not have permission to access this page.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 3: Register middleware alias in bootstrap/app.php**

In `bootstrap/app.php`, replace the empty `withMiddleware` block:

```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'role' => \App\Http\Middleware\EnsureRole::class,
    ]);
})
```

- [ ] **Step 4: Block inactive users at login**

In `app/Http/Controllers/Auth/AuthenticatedSessionController.php`, update the `store` method:

```php
public function store(LoginRequest $request): RedirectResponse
{
    $request->authenticate();

    // Block inactive accounts after credential check passes
    if (! $request->user()->is_active) {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        return back()->withErrors([
            'email' => 'Your account has been deactivated. Contact an administrator.',
        ]);
    }

    $request->session()->regenerate();

    return redirect()->intended(route('dashboard', absolute: false));
}
```

- [ ] **Step 5: Write RoleMiddlewareTest**

```php
<?php
// tests/Feature/RoleMiddlewareTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_users_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->get('/users')->assertOk();
    }

    public function test_staff_cannot_access_users_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff)->get('/users')->assertForbidden();
    }

    public function test_accounting_cannot_access_users_page(): void
    {
        $acc = User::factory()->create(['role' => 'accounting', 'is_active' => true]);
        $this->actingAs($acc)->get('/users')->assertForbidden();
    }

    public function test_staff_cannot_access_reports(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff)->get('/reports')->assertForbidden();
    }

    public function test_accounting_can_access_reports(): void
    {
        $acc = User::factory()->create(['role' => 'accounting', 'is_active' => true]);
        $this->actingAs($acc)->get('/reports')->assertOk();
    }

    public function test_inactive_user_gets_403_on_protected_routes(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'is_active' => false]);
        $this->actingAs($user)->get('/')->assertForbidden();
    }
}
```

- [ ] **Step 6: Run test (expect fail — routes not wired yet)**

```bash
php artisan test tests/Feature/RoleMiddlewareTest.php
```

Expected: Fail with 404 (routes don't exist yet). This confirms the test is exercising the right paths.

- [ ] **Step 7: Commit**

```bash
git add app/Models/User.php app/Http/Middleware/EnsureRole.php bootstrap/app.php \
        app/Http/Controllers/Auth/AuthenticatedSessionController.php \
        tests/Feature/RoleMiddlewareTest.php
git commit -m "feat(roles): EnsureRole middleware, User helpers, inactive-user block"
```

---

## Task 3: Petty cash migrations

**Files:**
- Create: `database/migrations/2026_05_27_000002_create_petty_cash_vouchers_table.php`
- Create: `database/migrations/2026_05_27_000003_create_petty_cash_items_table.php`

- [ ] **Step 1: Create vouchers migration**

```bash
php artisan make:migration create_petty_cash_vouchers_table
```

Rename to `2026_05_27_000002_create_petty_cash_vouchers_table.php` and replace contents:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('voucher_number')->unique();
            $table->string('or_number');
            $table->string('store_name');
            $table->string('releasing_officer');
            $table->decimal('requested_amount', 8, 2);
            $table->decimal('transport_fee', 8, 2)->default(0);
            $table->decimal('total_amount', 8, 2);
            $table->decimal('change_amount', 8, 2);
            $table->date('date_purchased');
            $table->enum('status', ['submitted', 'acknowledged', 'settled'])->default('submitted');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('change_returned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('change_returned_at')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_vouchers');
    }
};
```

- [ ] **Step 2: Create items migration**

```bash
php artisan make:migration create_petty_cash_items_table
```

Rename to `2026_05_27_000003_create_petty_cash_items_table.php` and replace contents:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('petty_cash_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('petty_cash_voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name');
            $table->decimal('qty', 8, 2);
            $table->string('unit');
            $table->decimal('unit_cost', 8, 2);
            $table->decimal('total_cost', 8, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('petty_cash_items');
    }
};
```

- [ ] **Step 3: Run migrations**

```bash
php artisan migrate
```

Expected: Both tables created.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_05_27_000002_create_petty_cash_vouchers_table.php \
        database/migrations/2026_05_27_000003_create_petty_cash_items_table.php
git commit -m "feat(petty-cash): add vouchers and items migrations"
```

---

## Task 4: Petty cash models

**Files:**
- Create: `app/Models/PettyCashVoucher.php`
- Create: `app/Models/PettyCashItem.php`

- [ ] **Step 1: Create PettyCashVoucher model**

```php
<?php
// app/Models/PettyCashVoucher.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PettyCashVoucher extends Model
{
    protected $fillable = [
        'voucher_number', 'or_number', 'store_name', 'releasing_officer',
        'requested_amount', 'transport_fee', 'total_amount', 'change_amount',
        'date_purchased', 'status', 'acknowledged_by', 'acknowledged_at',
        'change_returned_by', 'change_returned_at', 'created_by', 'remarks',
    ];

    protected $casts = [
        'date_purchased'    => 'date',
        'acknowledged_at'   => 'datetime',
        'change_returned_at'=> 'datetime',
        'requested_amount'  => 'decimal:2',
        'transport_fee'     => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'change_amount'     => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(PettyCashItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    public function changeReturnedBy()
    {
        return $this->belongsTo(User::class, 'change_returned_by');
    }

    /**
     * Generate next voucher number like PCV-2026-001.
     * Uses a DB lock to prevent duplicates under concurrent requests.
     */
    public static function generateVoucherNumber(): string
    {
        return DB::transaction(function () {
            $year = now()->year;
            $count = static::whereYear('created_at', $year)->lockForUpdate()->count();
            return sprintf('PCV-%d-%03d', $year, $count + 1);
        });
    }
}
```

- [ ] **Step 2: Create PettyCashItem model**

```php
<?php
// app/Models/PettyCashItem.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PettyCashItem extends Model
{
    protected $fillable = [
        'petty_cash_voucher_id', 'item_id',
        'item_name', 'qty', 'unit', 'unit_cost', 'total_cost',
    ];

    protected $casts = [
        'qty'        => 'decimal:2',
        'unit_cost'  => 'decimal:2',
        'total_cost' => 'decimal:2',
    ];

    public function voucher()
    {
        return $this->belongsTo(PettyCashVoucher::class, 'petty_cash_voucher_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/PettyCashVoucher.php app/Models/PettyCashItem.php
git commit -m "feat(petty-cash): PettyCashVoucher and PettyCashItem models"
```

---

## Task 5: PettyCashController (index, create, store)

**Files:**
- Create: `app/Http/Controllers/PettyCashController.php`

- [ ] **Step 1: Write the failing test for store**

```php
<?php
// tests/Feature/PettyCashVoucherTest.php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PettyCashVoucherTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff', 'is_active' => true]);
    }

    public function test_staff_can_submit_voucher_and_items_appear_in_inventory(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)->post('/petty-cash', [
            'or_number'          => 'OR-12345',
            'store_name'         => 'National Bookstore',
            'releasing_officer'  => 'Maria Reyes',
            'requested_amount'   => '2000',
            'transport_fee'      => '50',
            'date_purchased'     => '2026-05-27',
            'items'              => [
                ['item_name' => 'Bond Paper', 'qty' => '5', 'unit' => 'reams', 'unit_cost' => '200'],
                ['item_name' => 'Ballpen',    'qty' => '10','unit' => 'pcs',   'unit_cost' => '10'],
            ],
        ]);

        $response->assertRedirect('/petty-cash');

        // Voucher created
        $this->assertDatabaseHas('petty_cash_vouchers', [
            'or_number'         => 'OR-12345',
            'store_name'        => 'National Bookstore',
            'total_amount'      => 1150.00, // (5×200) + (10×10) + 50 transport
            'change_amount'     => 850.00,
            'status'            => 'submitted',
        ]);

        // Items auto-added to inventory
        $this->assertDatabaseHas('items', ['name' => 'Bond Paper', 'current_qty' => 5]);
        $this->assertDatabaseHas('items', ['name' => 'Ballpen',    'current_qty' => 10]);

        // Transactions created
        $this->assertEquals(2, Transaction::where('type', 'received')
            ->where('received_from', 'National Bookstore')->count());
    }

    public function test_overspend_is_rejected(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)->post('/petty-cash', [
            'or_number'         => 'OR-99',
            'store_name'        => 'SM',
            'releasing_officer' => 'Juan',
            'requested_amount'  => '500',
            'transport_fee'     => '0',
            'date_purchased'    => '2026-05-27',
            'items'             => [
                ['item_name' => 'Printer', 'qty' => '1', 'unit' => 'pcs', 'unit_cost' => '600'],
            ],
        ]);

        $response->assertSessionHasErrors('total');
        $this->assertDatabaseCount('petty_cash_vouchers', 0);
    }

    public function test_existing_item_qty_is_incremented_not_duplicated(): void
    {
        $staff = $this->staff();
        Item::create(['name' => 'Bond Paper', 'unit' => 'reams', 'current_qty' => 10, 'total_qty_received' => 10]);

        $this->actingAs($staff)->post('/petty-cash', [
            'or_number'         => 'OR-555',
            'store_name'        => 'Lim Store',
            'releasing_officer' => 'Pedro',
            'requested_amount'  => '2000',
            'transport_fee'     => '0',
            'date_purchased'    => '2026-05-27',
            'items'             => [
                ['item_name' => 'Bond Paper', 'qty' => '3', 'unit' => 'reams', 'unit_cost' => '180'],
            ],
        ]);

        $this->assertDatabaseHas('items', ['name' => 'Bond Paper', 'current_qty' => 13]);
        $this->assertEquals(1, Item::where('name', 'Bond Paper')->count());
    }

    public function test_accounting_cannot_create_voucher(): void
    {
        $acc = User::factory()->create(['role' => 'accounting', 'is_active' => true]);
        $this->actingAs($acc)->get('/petty-cash/create')->assertForbidden();
    }

    public function test_accounting_can_settle_acknowledged_voucher(): void
    {
        $staff = $this->staff();
        $acc   = User::factory()->create(['role' => 'accounting', 'is_active' => true]);

        // Create and acknowledge voucher first
        $this->actingAs($staff)->post('/petty-cash', [
            'or_number'         => 'OR-ACK',
            'store_name'        => 'Store',
            'releasing_officer' => 'Officer',
            'requested_amount'  => '500',
            'transport_fee'     => '0',
            'date_purchased'    => '2026-05-27',
            'items'             => [
                ['item_name' => 'Stapler', 'qty' => '1', 'unit' => 'pcs', 'unit_cost' => '200'],
            ],
        ]);

        $voucher = \App\Models\PettyCashVoucher::first();
        $this->actingAs($staff)->patch("/petty-cash/{$voucher->id}/acknowledge");

        // Now settle
        $this->actingAs($acc)->patch("/petty-cash/{$voucher->id}/settle")
             ->assertRedirect('/petty-cash');

        $this->assertDatabaseHas('petty_cash_vouchers', [
            'id'     => $voucher->id,
            'status' => 'settled',
        ]);
    }
}
```

- [ ] **Step 2: Run test — expect fail (controller doesn't exist)**

```bash
php artisan test tests/Feature/PettyCashVoucherTest.php
```

Expected: Fail with route not found errors.

- [ ] **Step 3: Create PettyCashController**

```php
<?php
// app/Http/Controllers/PettyCashController.php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PettyCashItem;
use App\Models\PettyCashVoucher;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PettyCashController extends Controller
{
    public function index(): View
    {
        $vouchers = PettyCashVoucher::with('creator')
            ->latest()
            ->paginate(20);

        return view('petty-cash.index', compact('vouchers'));
    }

    public function create(): View
    {
        return view('petty-cash.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'or_number'              => 'required|string|max:100',
            'store_name'             => 'required|string|max:255',
            'releasing_officer'      => 'required|string|max:255',
            'requested_amount'       => 'required|numeric|min:0.01|max:2000',
            'transport_fee'          => 'nullable|numeric|min:0',
            'date_purchased'         => 'required|date',
            'remarks'                => 'nullable|string|max:1000',
            'items'                  => 'required|array|min:1',
            'items.*.item_name'      => 'required|string|max:255',
            'items.*.qty'            => 'required|numeric|min:0.01',
            'items.*.unit'           => 'required|string|max:50',
            'items.*.unit_cost'      => 'required|numeric|min:0.01',
        ]);

        $transportFee = (float) ($data['transport_fee'] ?? 0);
        $itemsTotal   = collect($data['items'])->sum(fn($i) => $i['qty'] * $i['unit_cost']);
        $totalAmount  = $itemsTotal + $transportFee;
        $changeAmount = (float) $data['requested_amount'] - $totalAmount;

        if ($changeAmount < 0) {
            return back()
                ->withErrors(['total' => 'Total amount (₱' . number_format($totalAmount, 2) . ') exceeds the requested amount.'])
                ->withInput();
        }

        DB::transaction(function () use ($data, $transportFee, $totalAmount, $changeAmount) {
            $voucher = PettyCashVoucher::create([
                'voucher_number'    => PettyCashVoucher::generateVoucherNumber(),
                'or_number'         => $data['or_number'],
                'store_name'        => $data['store_name'],
                'releasing_officer' => $data['releasing_officer'],
                'requested_amount'  => $data['requested_amount'],
                'transport_fee'     => $transportFee,
                'total_amount'      => $totalAmount,
                'change_amount'     => $changeAmount,
                'date_purchased'    => $data['date_purchased'],
                'status'            => 'submitted',
                'created_by'        => auth()->id(),
                'remarks'           => $data['remarks'] ?? null,
            ]);

            foreach ($data['items'] as $line) {
                $qty       = (float) $line['qty'];
                $unitCost  = (float) $line['unit_cost'];
                $totalCost = $qty * $unitCost;
                $itemName  = trim($line['item_name']);
                $unit      = $line['unit'];

                // Match existing item (case-insensitive) or create new
                $item = Item::whereRaw('LOWER(TRIM(name)) = ?', [strtolower($itemName)])->first();

                if ($item) {
                    $item->increment('current_qty', $qty);
                    $item->increment('total_qty_received', $qty);
                } else {
                    $item = Item::create([
                        'name'               => $itemName,
                        'unit'               => $unit,
                        'current_qty'        => $qty,
                        'total_qty_received' => $qty,
                        'created_by'         => auth()->id(),
                    ]);
                }

                // Inventory received transaction
                Transaction::create([
                    'type'                => 'received',
                    'item_id'             => $item->id,
                    'item_name_snapshot'  => $itemName,
                    'qty'                 => $qty,
                    'unit'                => $unit,
                    'received_from'       => $data['store_name'],
                    'ris_iar_number'      => $data['or_number'],
                    'date_received'       => $data['date_purchased'],
                    'received_by_user_id' => auth()->id(),
                ]);

                PettyCashItem::create([
                    'petty_cash_voucher_id' => $voucher->id,
                    'item_id'               => $item->id,
                    'item_name'             => $itemName,
                    'qty'                   => $qty,
                    'unit'                  => $unit,
                    'unit_cost'             => $unitCost,
                    'total_cost'            => $totalCost,
                ]);
            }
        });

        return redirect()->route('petty-cash.index')
            ->with('success', 'Voucher submitted and inventory updated.');
    }

    public function show(PettyCashVoucher $pettyCash): View
    {
        $pettyCash->load(['items.item', 'creator', 'acknowledgedBy', 'changeReturnedBy']);
        return view('petty-cash.show', compact('pettyCash'));
    }

    public function acknowledge(PettyCashVoucher $pettyCash): RedirectResponse
    {
        abort_if($pettyCash->status !== 'submitted', 422, 'Voucher is not in submitted state.');

        $pettyCash->update([
            'status'          => 'acknowledged',
            'acknowledged_by' => auth()->id(),
            'acknowledged_at' => now(),
        ]);

        return redirect()->route('petty-cash.show', $pettyCash)
            ->with('success', 'Voucher acknowledged.');
    }

    public function settle(PettyCashVoucher $pettyCash): RedirectResponse
    {
        abort_if($pettyCash->status !== 'acknowledged', 422, 'Voucher must be acknowledged first.');

        $pettyCash->update([
            'status'             => 'settled',
            'change_returned_by' => auth()->id(),
            'change_returned_at' => now(),
        ]);

        return redirect()->route('petty-cash.index')
            ->with('success', 'Voucher settled. Change return recorded.');
    }

    public function destroy(PettyCashVoucher $pettyCash): RedirectResponse
    {
        $pettyCash->delete();
        return redirect()->route('petty-cash.index')
            ->with('success', 'Voucher deleted.');
    }

    public function print(PettyCashVoucher $pettyCash): View
    {
        $pettyCash->load(['items', 'creator', 'acknowledgedBy', 'changeReturnedBy']);
        return view('petty-cash.print', compact('pettyCash'));
    }
}
```

- [ ] **Step 4: Commit controller**

```bash
git add app/Http/Controllers/PettyCashController.php tests/Feature/PettyCashVoucherTest.php
git commit -m "feat(petty-cash): PettyCashController with auto-inventory store logic"
```

---

## Task 6: Routes

**Files:**
- Modify: `routes/web.php`

- [ ] **Step 1: Replace routes/web.php**

```php
<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\PettyCashController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReceiveController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\AcknowledgeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// All authenticated routes also require is_active check via EnsureRole
Route::middleware(['auth'])->group(function () {

    // ── Available to ALL roles ──────────────────────────────────────────────
    Route::middleware('role:admin,staff,accounting')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/receive', [ReceiveController::class, 'index'])->name('receive.index');
        Route::post('/receive', [ReceiveController::class, 'store'])->name('receive.store');
        Route::get('/release', [ReleaseController::class, 'index'])->name('release.index');
        Route::post('/release', [ReleaseController::class, 'store'])->name('release.store');
        Route::get('/acknowledge', [AcknowledgeController::class, 'index'])->name('acknowledge.index');
        Route::patch('/acknowledge/{transaction}', [AcknowledgeController::class, 'update'])->name('acknowledge.update');
        Route::get('/transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transactions/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::get('/items', [ItemController::class, 'index'])->name('items.index');
        Route::get('/items/{item}', [ItemController::class, 'show'])->name('items.show');

        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Petty cash — view index and show for all roles
        Route::get('/petty-cash', [PettyCashController::class, 'index'])->name('petty-cash.index');
        Route::get('/petty-cash/{pettyCash}', [PettyCashController::class, 'show'])->name('petty-cash.show');
        Route::get('/petty-cash/{pettyCash}/print', [PettyCashController::class, 'print'])->name('petty-cash.print');
    });

    // ── Staff + Admin: create/acknowledge vouchers ─────────────────────────
    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/petty-cash/create', [PettyCashController::class, 'create'])->name('petty-cash.create');
        Route::post('/petty-cash', [PettyCashController::class, 'store'])->name('petty-cash.store');
        Route::patch('/petty-cash/{pettyCash}/acknowledge', [PettyCashController::class, 'acknowledge'])->name('petty-cash.acknowledge');
    });

    // ── Accounting + Admin: settle vouchers + reports ─────────────────────
    Route::middleware('role:admin,accounting')->group(function () {
        Route::patch('/petty-cash/{pettyCash}/settle', [PettyCashController::class, 'settle'])->name('petty-cash.settle');
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/inventory/{type}', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/reports/petty-cash/{type}', [ReportController::class, 'pettyCash'])->name('reports.petty-cash');
    });

    // ── Admin only ─────────────────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::delete('/petty-cash/{pettyCash}', [PettyCashController::class, 'destroy'])->name('petty-cash.destroy');
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/users/{user}/deactivate', [UserController::class, 'deactivate'])->name('users.deactivate');
    });
});

require __DIR__.'/auth.php';
```

- [ ] **Step 2: Run role middleware tests**

```bash
php artisan test tests/Feature/RoleMiddlewareTest.php
```

Expected: All 6 tests pass (routes now exist and middleware is wired).

- [ ] **Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat(routes): role-gated route groups for petty cash, reports, users"
```

---

## Task 7: Petty cash create view

**Files:**
- Create: `resources/views/petty-cash/create.blade.php`

- [ ] **Step 1: Create the view**

```blade
{{-- resources/views/petty-cash/create.blade.php --}}
<x-app-layout>
    <x-page-header title="New Petty Cash Voucher" subtitle="Record a petty cash purchase and update inventory.">
        <x-slot name="actions">
            <x-button href="{{ route('petty-cash.index') }}" variant="secondary">Cancel</x-button>
        </x-slot>
    </x-page-header>

    <form method="POST" action="{{ route('petty-cash.store') }}"
          x-data="{
              items: [{ item_name: '', qty: '', unit: '', unit_cost: '' }],
              transport: 0,
              requested: 0,
              get itemsTotal() {
                  return this.items.reduce((s, i) => s + (parseFloat(i.qty)||0) * (parseFloat(i.unit_cost)||0), 0);
              },
              get totalAmount() { return this.itemsTotal + (parseFloat(this.transport)||0); },
              get changeAmount() { return (parseFloat(this.requested)||0) - this.totalAmount; },
              addLine() { this.items.push({ item_name: '', qty: '', unit: '', unit_cost: '' }); },
              removeLine(idx) { if (this.items.length > 1) this.items.splice(idx, 1); },
          }">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- Header info --}}
            <x-bento-card class="lg:col-span-2 space-y-4">
                <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Voucher Details</h2>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-label for="date_purchased">Date Purchased</x-label>
                        <x-input type="date" id="date_purchased" name="date_purchased"
                                 value="{{ old('date_purchased', today()->toDateString()) }}" required />
                        @error('date_purchased') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-label for="or_number">Official Receipt No.</x-label>
                        <x-input id="or_number" name="or_number" value="{{ old('or_number') }}"
                                 placeholder="OR-12345" required />
                        @error('or_number') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-label for="store_name">Store / Supplier Name</x-label>
                        <x-input id="store_name" name="store_name" value="{{ old('store_name') }}"
                                 placeholder="National Bookstore" required />
                        @error('store_name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <x-label for="releasing_officer">Releasing Officer</x-label>
                        <x-input id="releasing_officer" name="releasing_officer" value="{{ old('releasing_officer') }}"
                                 placeholder="Accounting officer name" required />
                        @error('releasing_officer') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <x-label for="remarks">Remarks (optional)</x-label>
                    <x-textarea id="remarks" name="remarks" rows="2">{{ old('remarks') }}</x-textarea>
                </div>
            </x-bento-card>

            {{-- Financial summary --}}
            <x-bento-card class="space-y-4">
                <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Financials</h2>

                <div>
                    <x-label for="requested_amount">Amount Requested (max ₱2,000)</x-label>
                    <x-input type="number" id="requested_amount" name="requested_amount" step="0.01"
                             min="0.01" max="2000" x-model="requested"
                             value="{{ old('requested_amount') }}" required />
                    @error('requested_amount') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="transport_fee">Transport Fee (optional)</x-label>
                    <x-input type="number" id="transport_fee" name="transport_fee" step="0.01"
                             min="0" x-model="transport" value="{{ old('transport_fee', '0') }}" />
                </div>

                <div class="pt-2 border-t border-surface-border space-y-1 text-sm">
                    <div class="flex justify-between text-ink-muted">
                        <span>Items Total</span>
                        <span x-text="'₱' + itemsTotal.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-ink-muted">
                        <span>Transport</span>
                        <span x-text="'₱' + (parseFloat(transport)||0).toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between font-semibold text-ink-heading">
                        <span>Total Spent</span>
                        <span x-text="'₱' + totalAmount.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between font-bold text-lg"
                         :class="changeAmount >= 0 ? 'text-primary-700' : 'text-danger'">
                        <span>Change Due</span>
                        <span x-text="'₱' + changeAmount.toFixed(2)"></span>
                    </div>
                </div>

                @error('total') <p class="text-xs text-danger">{{ $message }}</p> @enderror

                <x-button type="submit" class="w-full" variant="primary">Submit Voucher</x-button>
            </x-bento-card>

            {{-- Line items --}}
            <x-bento-card class="lg:col-span-3">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Items Purchased</h2>
                    <x-button type="button" variant="secondary" size="sm" @click="addLine()">+ Add Item</x-button>
                </div>

                <div class="space-y-3">
                    <template x-for="(item, idx) in items" :key="idx">
                        <div class="grid grid-cols-12 gap-3 items-end">
                            <div class="col-span-4">
                                <x-label>Item Name</x-label>
                                <input type="text" :name="`items[${idx}][item_name]`" x-model="item.item_name"
                                       placeholder="Bond Paper"
                                       class="w-full rounded-lg border border-surface-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required />
                            </div>
                            <div class="col-span-2">
                                <x-label>Qty</x-label>
                                <input type="number" :name="`items[${idx}][qty]`" x-model="item.qty"
                                       step="0.01" min="0.01" placeholder="5"
                                       class="w-full rounded-lg border border-surface-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required />
                            </div>
                            <div class="col-span-2">
                                <x-label>Unit</x-label>
                                <input type="text" :name="`items[${idx}][unit]`" x-model="item.unit"
                                       placeholder="reams"
                                       class="w-full rounded-lg border border-surface-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required />
                            </div>
                            <div class="col-span-2">
                                <x-label>Unit Cost (₱)</x-label>
                                <input type="number" :name="`items[${idx}][unit_cost]`" x-model="item.unit_cost"
                                       step="0.01" min="0.01" placeholder="200"
                                       class="w-full rounded-lg border border-surface-border bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" required />
                            </div>
                            <div class="col-span-1 text-sm text-ink-muted pt-1">
                                <span x-text="'₱' + ((parseFloat(item.qty)||0)*(parseFloat(item.unit_cost)||0)).toFixed(2)"></span>
                            </div>
                            <div class="col-span-1">
                                <button type="button" @click="removeLine(idx)"
                                        class="text-danger hover:text-rose-700 transition" title="Remove">
                                    <x-heroicon-o-trash class="w-5 h-5"/>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </x-bento-card>

        </div>
    </form>
</x-app-layout>
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/petty-cash/create.blade.php
git commit -m "feat(petty-cash): create view with Alpine dynamic line items"
```

---

## Task 8: Petty cash index + show views

**Files:**
- Create: `resources/views/petty-cash/index.blade.php`
- Create: `resources/views/petty-cash/show.blade.php`

- [ ] **Step 1: Create index view**

```blade
{{-- resources/views/petty-cash/index.blade.php --}}
<x-app-layout>
    <x-page-header title="Petty Cash Vouchers" subtitle="Track cash advances and purchases.">
        <x-slot name="actions">
            @if(auth()->user()->canCreateVoucher())
                <x-button href="{{ route('petty-cash.create') }}" variant="primary">New Voucher</x-button>
            @endif
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')" />
    @endif

    @if($vouchers->isEmpty())
        <x-empty-state icon="banknotes" title="No vouchers yet"
                       description="Submit a petty cash voucher to get started." />
    @else
        <x-bento-card>
            <x-table>
                <x-slot name="head">
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Voucher #</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Store</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">OR #</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-ink-muted uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-ink-muted uppercase">Change</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">By</th>
                    <th class="px-4 py-3"></th>
                </x-slot>
                @foreach($vouchers as $v)
                    <x-table.row>
                        <td class="px-4 py-3 font-mono text-sm text-primary-700">{{ $v->voucher_number }}</td>
                        <td class="px-4 py-3 text-sm">{{ $v->date_purchased->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-sm">{{ $v->store_name }}</td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $v->or_number }}</td>
                        <td class="px-4 py-3 text-sm text-right font-medium">₱{{ number_format($v->total_amount, 2) }}</td>
                        <td class="px-4 py-3 text-sm text-right {{ $v->change_amount > 0 ? 'text-amber-600 font-semibold' : 'text-ink-muted' }}">
                            ₱{{ number_format($v->change_amount, 2) }}
                        </td>
                        <td class="px-4 py-3">
                            <x-status-badge :status="$v->status" />
                        </td>
                        <td class="px-4 py-3 text-sm text-ink-muted">{{ $v->creator->name }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('petty-cash.show', $v) }}"
                               class="text-xs text-primary-600 hover:underline">View</a>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
            <div class="px-4 py-3">{{ $vouchers->links() }}</div>
        </x-bento-card>
    @endif
</x-app-layout>
```

- [ ] **Step 2: Create show view**

```blade
{{-- resources/views/petty-cash/show.blade.php --}}
<x-app-layout>
    <x-page-header :title="$pettyCash->voucher_number" subtitle="Petty Cash Voucher">
        <x-slot name="actions">
            <x-button href="{{ route('petty-cash.print', $pettyCash) }}" variant="secondary" target="_blank">Print</x-button>
            <x-button href="{{ route('petty-cash.index') }}" variant="secondary">Back</x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')" />
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Voucher header --}}
        <x-bento-card class="lg:col-span-2 space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Voucher Info</h2>
                <x-status-badge :status="$pettyCash->status" />
            </div>
            <dl class="grid grid-cols-2 gap-x-6 gap-y-2 text-sm">
                <dt class="text-ink-muted">Date Purchased</dt>
                <dd>{{ $pettyCash->date_purchased->format('F d, Y') }}</dd>
                <dt class="text-ink-muted">OR Number</dt>
                <dd class="font-mono">{{ $pettyCash->or_number }}</dd>
                <dt class="text-ink-muted">Store / Supplier</dt>
                <dd>{{ $pettyCash->store_name }}</dd>
                <dt class="text-ink-muted">Releasing Officer</dt>
                <dd>{{ $pettyCash->releasing_officer }}</dd>
                <dt class="text-ink-muted">Prepared By</dt>
                <dd>{{ $pettyCash->creator->name }}</dd>
                @if($pettyCash->remarks)
                    <dt class="text-ink-muted">Remarks</dt>
                    <dd>{{ $pettyCash->remarks }}</dd>
                @endif
            </dl>

            {{-- Items table --}}
            <div class="mt-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-surface-border text-left text-xs text-ink-muted uppercase">
                            <th class="pb-2">Item</th>
                            <th class="pb-2 text-right">Qty</th>
                            <th class="pb-2">Unit</th>
                            <th class="pb-2 text-right">Unit Cost</th>
                            <th class="pb-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-surface-border">
                        @foreach($pettyCash->items as $line)
                            <tr>
                                <td class="py-2">{{ $line->item_name }}</td>
                                <td class="py-2 text-right">{{ $line->qty }}</td>
                                <td class="py-2 text-ink-muted">{{ $line->unit }}</td>
                                <td class="py-2 text-right">₱{{ number_format($line->unit_cost, 2) }}</td>
                                <td class="py-2 text-right font-medium">₱{{ number_format($line->total_cost, 2) }}</td>
                            </tr>
                        @endforeach
                        @if($pettyCash->transport_fee > 0)
                            <tr class="text-ink-muted">
                                <td colspan="4" class="py-2 italic">Transport Fee</td>
                                <td class="py-2 text-right">₱{{ number_format($pettyCash->transport_fee, 2) }}</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
        </x-bento-card>

        {{-- Financial summary + actions --}}
        <div class="space-y-4">
            <x-bento-card class="space-y-3 text-sm">
                <h2 class="text-sm font-semibold text-ink-heading uppercase tracking-wide">Summary</h2>
                <div class="space-y-1">
                    <div class="flex justify-between text-ink-muted">
                        <span>Amount Requested</span>
                        <span>₱{{ number_format($pettyCash->requested_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-ink-muted">
                        <span>Total Spent</span>
                        <span>₱{{ number_format($pettyCash->total_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-base border-t border-surface-border pt-2
                                {{ $pettyCash->change_amount > 0 ? 'text-amber-700' : 'text-ink-heading' }}">
                        <span>Change Due</span>
                        <span>₱{{ number_format($pettyCash->change_amount, 2) }}</span>
                    </div>
                </div>
            </x-bento-card>

            {{-- Acknowledge action --}}
            @if($pettyCash->status === 'submitted' && auth()->user()->canCreateVoucher())
                <x-bento-card class="space-y-2">
                    <p class="text-sm text-ink-muted">Confirm that the purchase details and change amount are correct.</p>
                    <form method="POST" action="{{ route('petty-cash.acknowledge', $pettyCash) }}">
                        @csrf @method('PATCH')
                        <x-button type="submit" variant="primary" class="w-full">Acknowledge Voucher</x-button>
                    </form>
                </x-bento-card>
            @endif

            {{-- Settle action --}}
            @if($pettyCash->status === 'acknowledged' && auth()->user()->canSettleVoucher())
                <x-bento-card class="space-y-2 border-l-4 border-amber-400">
                    <p class="text-sm font-medium text-amber-700">
                        Change of ₱{{ number_format($pettyCash->change_amount, 2) }} must be returned.
                    </p>
                    <p class="text-xs text-ink-muted">Click below once the change has been physically received.</p>
                    <form method="POST" action="{{ route('petty-cash.settle', $pettyCash) }}">
                        @csrf @method('PATCH')
                        <x-button type="submit" variant="primary" class="w-full">Mark Change Returned</x-button>
                    </form>
                </x-bento-card>
            @endif

            {{-- Settled info --}}
            @if($pettyCash->status === 'settled')
                <x-bento-card class="space-y-1 text-sm border-l-4 border-primary-500">
                    <p class="font-medium text-primary-700">Fully Settled</p>
                    <p class="text-ink-muted">Change returned by {{ $pettyCash->changeReturnedBy?->name }}</p>
                    <p class="text-ink-muted">{{ $pettyCash->change_returned_at?->format('M d, Y g:i A') }}</p>
                </x-bento-card>
            @endif

            {{-- Admin delete --}}
            @if(auth()->user()->isAdmin())
                <form method="POST" action="{{ route('petty-cash.destroy', $pettyCash) }}"
                      x-data onsubmit="return confirm('Delete this voucher permanently?')">
                    @csrf @method('DELETE')
                    <x-button type="submit" variant="danger" class="w-full">Delete Voucher</x-button>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Commit**

```bash
git add resources/views/petty-cash/index.blade.php resources/views/petty-cash/show.blade.php
git commit -m "feat(petty-cash): index and show views"
```

---

## Task 9: Petty cash print view

**Files:**
- Create: `resources/views/petty-cash/print.blade.php`

- [ ] **Step 1: Create print view** (standalone page, no app layout)

```blade
{{-- resources/views/petty-cash/print.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $pettyCash->voucher_number }} — Petty Cash Voucher</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; font-size: 12px; color: #111; padding: 32px; }
        h1 { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        .subtitle { font-size: 11px; color: #555; margin-bottom: 16px; }
        .grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 24px; margin-bottom: 16px; }
        .label { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { padding: 6px 8px; border: 1px solid #ddd; }
        th { background: #f3f4f6; text-align: left; font-weight: 600; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; background: #f9fafb; }
        .change-row td { font-weight: bold; font-size: 14px; background: #fef3c7; }
        .signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 40px; }
        .sig-block { border-top: 1px solid #111; padding-top: 4px; text-align: center; font-size: 11px; }
        @media print { button { display: none; } }
    </style>
</head>
<body>

<h1>Petty Cash Voucher</h1>
<p class="subtitle">MIS Office — La Union Medical Center</p>

<div class="grid2">
    <div><span class="label">Voucher No:</span> <strong>{{ $pettyCash->voucher_number }}</strong></div>
    <div><span class="label">Date:</span> {{ $pettyCash->date_purchased->format('F d, Y') }}</div>
    <div><span class="label">OR Number:</span> {{ $pettyCash->or_number }}</div>
    <div><span class="label">Store / Supplier:</span> {{ $pettyCash->store_name }}</div>
    <div><span class="label">Releasing Officer:</span> {{ $pettyCash->releasing_officer }}</div>
    <div><span class="label">Amount Requested:</span> ₱{{ number_format($pettyCash->requested_amount, 2) }}</div>
    @if($pettyCash->remarks)
        <div style="grid-column: span 2"><span class="label">Remarks:</span> {{ $pettyCash->remarks }}</div>
    @endif
</div>

<table>
    <thead>
        <tr>
            <th>Item</th>
            <th class="text-right">Qty</th>
            <th>Unit</th>
            <th class="text-right">Unit Cost</th>
            <th class="text-right">Total</th>
        </tr>
    </thead>
    <tbody>
        @foreach($pettyCash->items as $line)
            <tr>
                <td>{{ $line->item_name }}</td>
                <td class="text-right">{{ $line->qty }}</td>
                <td>{{ $line->unit }}</td>
                <td class="text-right">₱{{ number_format($line->unit_cost, 2) }}</td>
                <td class="text-right">₱{{ number_format($line->total_cost, 2) }}</td>
            </tr>
        @endforeach
        @if($pettyCash->transport_fee > 0)
            <tr>
                <td colspan="4" style="color:#555;font-style:italic">Transport Fee</td>
                <td class="text-right">₱{{ number_format($pettyCash->transport_fee, 2) }}</td>
            </tr>
        @endif
        <tr class="total-row">
            <td colspan="4" class="text-right">Total Amount Spent</td>
            <td class="text-right">₱{{ number_format($pettyCash->total_amount, 2) }}</td>
        </tr>
        <tr class="change-row">
            <td colspan="4" class="text-right">Change Returned to Accounting</td>
            <td class="text-right">₱{{ number_format($pettyCash->change_amount, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="signatures">
    <div class="sig-block">
        <strong>{{ $pettyCash->creator->name }}</strong><br>Prepared by
    </div>
    <div class="sig-block">
        @if($pettyCash->acknowledgedBy)
            <strong>{{ $pettyCash->acknowledgedBy->name }}</strong>
        @else
            &nbsp;
        @endif
        <br>Acknowledged by
    </div>
    <div class="sig-block">
        {{ $pettyCash->releasing_officer }}<br>Released by (Accounting)
    </div>
</div>

<p style="margin-top:32px; text-align:right">
    <button onclick="window.print()" style="padding:6px 16px;cursor:pointer">🖨 Print</button>
</p>

</body>
</html>
```

- [ ] **Step 2: Run petty cash tests**

```bash
php artisan test tests/Feature/PettyCashVoucherTest.php
```

Expected: All 5 tests pass.

- [ ] **Step 3: Commit**

```bash
git add resources/views/petty-cash/print.blade.php
git commit -m "feat(petty-cash): printable voucher view with signatures"
```

---

## Task 10: Dashboard updates

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `resources/views/dashboard.blade.php`

- [ ] **Step 1: Update DashboardController**

Add petty cash stats to the `index` method (add imports and new variables alongside existing ones):

```php
use App\Models\PettyCashVoucher;

// Inside index(), add after existing queries:
$pcThisMonth = PettyCashVoucher::whereMonth('created_at', now()->month)
    ->whereYear('created_at', now()->year)
    ->whereIn('status', ['submitted', 'acknowledged', 'settled'])
    ->sum('total_amount');

$pcVouchersThisMonth = PettyCashVoucher::whereMonth('created_at', now()->month)
    ->whereYear('created_at', now()->year)
    ->count();

$pcPendingAck = PettyCashVoucher::where('status', 'submitted')->count();

$pcPendingSettle = PettyCashVoucher::where('status', 'acknowledged')->count();

$recentVouchers = PettyCashVoucher::with('creator')
    ->latest()
    ->limit(5)
    ->get();
```

Update the `compact()` call to include:
```php
return view('dashboard', compact(
    'totalInStock', 'totalReleased', 'pendingAck', 'acknowledged',
    'pendingTransactions', 'weeklyActivity', 'topOffice', 'topItem',
    'pcThisMonth', 'pcVouchersThisMonth', 'pcPendingAck', 'pcPendingSettle', 'recentVouchers',
));
```

- [ ] **Step 2: Add petty cash tiles to dashboard view**

In `resources/views/dashboard.blade.php`, add after the existing stat tiles section:

```blade
{{-- Petty Cash stat tiles --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mt-6">
    <x-stat-tile label="Petty Cash This Month" :value="'₱' . number_format($pcThisMonth, 2)" icon="banknotes" color="amber" />
    <x-stat-tile label="Vouchers This Month" :value="$pcVouchersThisMonth" icon="document-text" color="primary" />
    @if(auth()->user()->canCreateVoucher())
        <x-stat-tile label="Pending Acknowledgement" :value="$pcPendingAck" icon="clock" color="rose" />
    @endif
    @if(auth()->user()->canSettleVoucher())
        <x-stat-tile label="Pending Settlement" :value="$pcPendingSettle" icon="banknotes" color="amber" />
    @endif
</div>

{{-- Recent vouchers --}}
@if($recentVouchers->isNotEmpty())
<x-bento-card class="mt-6">
    <h3 class="text-sm font-semibold text-ink-heading uppercase tracking-wide mb-3">Recent Petty Cash</h3>
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs text-ink-muted uppercase border-b border-surface-border">
                <th class="pb-2">Voucher</th>
                <th class="pb-2">Store</th>
                <th class="pb-2 text-right">Amount</th>
                <th class="pb-2 text-right">Change</th>
                <th class="pb-2">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-surface-border">
            @foreach($recentVouchers as $v)
                <tr>
                    <td class="py-2 font-mono text-primary-700">
                        <a href="{{ route('petty-cash.show', $v) }}" class="hover:underline">{{ $v->voucher_number }}</a>
                    </td>
                    <td class="py-2 text-ink-muted">{{ $v->store_name }}</td>
                    <td class="py-2 text-right">₱{{ number_format($v->total_amount, 2) }}</td>
                    <td class="py-2 text-right {{ $v->change_amount > 0 ? 'text-amber-600' : 'text-ink-muted' }}">
                        ₱{{ number_format($v->change_amount, 2) }}
                    </td>
                    <td class="py-2"><x-status-badge :status="$v->status" /></td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-bento-card>
@endif
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/DashboardController.php resources/views/dashboard.blade.php
git commit -m "feat(dashboard): petty cash stat tiles and recent vouchers"
```

---

## Task 11: Sidebar role-gated nav + badges

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Update sidebar in app.blade.php**

Replace the `$nav` PHP block and the `@foreach` nav loop with this:

```blade
@php
    $user = auth()->user();
    $nav = [
        ['route' => 'dashboard',          'label' => 'Dashboard',    'icon' => 'home',                    'match' => '/'],
        ['route' => 'receive.index',      'label' => 'Receive',      'icon' => 'arrow-down-tray',         'match' => 'receive'],
        ['route' => 'release.index',      'label' => 'Release',      'icon' => 'arrow-up-tray',           'match' => 'release'],
        ['route' => 'acknowledge.index',  'label' => 'Acknowledge',  'icon' => 'check-circle',            'match' => 'acknowledge'],
        ['route' => 'transactions.index', 'label' => 'Transactions', 'icon' => 'clipboard-document-list', 'match' => 'transactions*'],
        ['route' => 'items.index',        'label' => 'Inventory',    'icon' => 'cube',                    'match' => 'items*'],
    ];

    // Petty cash badge counts
    $pcBadge = 0;
    if ($user->canCreateVoucher()) {
        $pcBadge = \App\Models\PettyCashVoucher::where('status', 'submitted')->count();
    } elseif ($user->canSettleVoucher()) {
        $pcBadge = \App\Models\PettyCashVoucher::where('status', 'acknowledged')->count();
    }
@endphp
```

Then after the nav `@foreach` loop (existing items), add role-gated links:

```blade
{{-- Petty Cash (all roles) --}}
@php $pcActive = request()->is('petty-cash*'); @endphp
<a href="{{ route('petty-cash.index') }}"
   class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
          {{ $pcActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
    @if($pcActive)
        <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
    @endif
    <span class="relative">
        <x-heroicon-o-banknotes class="w-5 h-5 shrink-0"/>
        @if($pcBadge > 0)
            <span class="absolute -top-1 -right-1 bg-rose-500 text-white text-[10px] rounded-full w-4 h-4 flex items-center justify-center">
                {{ $pcBadge > 9 ? '9+' : $pcBadge }}
            </span>
        @endif
    </span>
    <span x-show="!collapsed" x-transition.opacity>Petty Cash</span>
</a>

{{-- Reports (accounting + admin) --}}
@if($user->canAccessReports())
    @php $repActive = request()->is('reports*'); @endphp
    <a href="{{ route('reports.index') }}"
       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
              {{ $repActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
        @if($repActive)
            <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
        @endif
        <x-heroicon-o-chart-bar class="w-5 h-5 shrink-0"/>
        <span x-show="!collapsed" x-transition.opacity>Reports</span>
    </a>
@endif

{{-- Users (admin only) --}}
@if($user->canManageUsers())
    @php $usersActive = request()->is('users*'); @endphp
    <a href="{{ route('users.index') }}"
       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
              {{ $usersActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
        @if($usersActive)
            <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
        @endif
        <x-heroicon-o-users class="w-5 h-5 shrink-0"/>
        <span x-show="!collapsed" x-transition.opacity>Users</span>
    </a>
@endif
```

- [ ] **Step 2: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat(sidebar): role-gated nav links and petty cash badge"
```

---

## Task 12: ReportController + Reports view

**Files:**
- Create: `app/Http/Controllers/ReportController.php`
- Create: `resources/views/reports/index.blade.php`

- [ ] **Step 1: Create ReportController**

```php
<?php
// app/Http/Controllers/ReportController.php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\PettyCashVoucher;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        return view('reports.index', [
            'tab'  => null,
            'type' => null,
            'rows' => [],
            'headers' => [],
        ]);
    }

    public function inventory(Request $request, string $type): View|Response
    {
        [$headers, $rows, $title] = match ($type) {
            'received'       => $this->receivedItems($request),
            'released'       => $this->releasedItems($request),
            'movement'       => $this->stockMovement($request),
            'snapshot'       => $this->stockSnapshot(),
            'acknowledgement'=> $this->acknowledgementStatus($request),
            default          => abort(404),
        };

        if ($request->boolean('export')) {
            return $this->csvResponse($headers, $rows, $type);
        }

        return view('reports.index', compact('headers', 'rows', 'title', 'type') + ['tab' => 'inventory']);
    }

    public function pettyCash(Request $request, string $type): View|Response
    {
        [$headers, $rows, $title] = match ($type) {
            'ledger'      => $this->voucherLedger($request),
            'monthly'     => $this->monthlySummary($request),
            'outstanding' => $this->outstandingChanges(),
            'purchases'   => $this->itemPurchaseHistory($request),
            default       => abort(404),
        };

        if ($request->boolean('export')) {
            return $this->csvResponse($headers, $rows, $type);
        }

        return view('reports.index', compact('headers', 'rows', 'title', 'type') + ['tab' => 'petty-cash']);
    }

    // ── Inventory reports ──────────────────────────────────────────────────

    private function receivedItems(Request $request): array
    {
        $q = Transaction::where('type', 'received')
            ->with('item')
            ->latest('date_received');

        if ($request->filled('from')) $q->whereDate('date_received', '>=', $request->from);
        if ($request->filled('to'))   $q->whereDate('date_received', '<=', $request->to);
        if ($request->filled('item')) $q->where('item_name_snapshot', 'like', '%' . $request->item . '%');

        $rows = $q->get()->map(fn($t) => [
            $t->date_received,
            $t->item_name_snapshot,
            $t->qty,
            $t->unit,
            $t->received_from,
            $t->ris_iar_number,
        ])->toArray();

        return [
            ['Date', 'Item', 'Qty', 'Unit', 'Received From', 'RIS/IAR #'],
            $rows,
            'Received Items',
        ];
    }

    private function releasedItems(Request $request): array
    {
        $q = Transaction::where('type', 'released')
            ->latest('date_released');

        if ($request->filled('from')) $q->whereDate('date_released', '>=', $request->from);
        if ($request->filled('to'))   $q->whereDate('date_released', '<=', $request->to);
        if ($request->filled('item')) $q->where('item_name_snapshot', 'like', '%' . $request->item . '%');
        if ($request->filled('office')) $q->where('released_to_office', 'like', '%' . $request->office . '%');

        $rows = $q->get()->map(fn($t) => [
            $t->date_released,
            $t->item_name_snapshot,
            $t->qty,
            $t->unit,
            $t->released_to_office,
            $t->receiver_name,
            $t->acknowledgment_status,
        ])->toArray();

        return [
            ['Date', 'Item', 'Qty', 'Unit', 'Office', 'Receiver', 'Ack Status'],
            $rows,
            'Released Items',
        ];
    }

    private function stockMovement(Request $request): array
    {
        $itemName = $request->input('item', '');
        $q = Transaction::when($itemName, fn($q) => $q->where('item_name_snapshot', 'like', '%' . $itemName . '%'))
            ->oldest('created_at');

        if ($request->filled('from')) $q->where(fn($q) =>
            $q->whereDate('date_received', '>=', $request->from)
              ->orWhereDate('date_released', '>=', $request->from));
        if ($request->filled('to')) $q->where(fn($q) =>
            $q->whereDate('date_received', '<=', $request->to)
              ->orWhereDate('date_released', '<=', $request->to));

        $rows = $q->get()->map(fn($t) => [
            $t->type === 'received' ? $t->date_received : $t->date_released,
            $t->item_name_snapshot,
            $t->type,
            $t->qty,
            $t->unit,
        ])->toArray();

        return [
            ['Date', 'Item', 'Type', 'Qty', 'Unit'],
            $rows,
            'Stock Movement',
        ];
    }

    private function stockSnapshot(): array
    {
        $rows = Item::orderBy('name')->get()->map(fn($i) => [
            $i->name,
            $i->unit,
            $i->current_qty,
            $i->total_qty_received,
        ])->toArray();

        return [
            ['Item', 'Unit', 'Current Qty', 'Total Received'],
            $rows,
            'Current Stock Snapshot',
        ];
    }

    private function acknowledgementStatus(Request $request): array
    {
        $q = Transaction::where('type', 'released');
        if ($request->filled('status')) $q->where('acknowledgment_status', $request->status);
        if ($request->filled('from'))   $q->whereDate('date_released', '>=', $request->from);
        if ($request->filled('to'))     $q->whereDate('date_released', '<=', $request->to);

        $rows = $q->latest('date_released')->get()->map(fn($t) => [
            $t->date_released,
            $t->item_name_snapshot,
            $t->qty,
            $t->released_to_office,
            $t->receiver_name,
            $t->acknowledgment_status,
            $t->acknowledged_date,
        ])->toArray();

        return [
            ['Date Released', 'Item', 'Qty', 'Office', 'Receiver', 'Status', 'Ack Date'],
            $rows,
            'Acknowledgement Status',
        ];
    }

    // ── Petty cash reports ─────────────────────────────────────────────────

    private function voucherLedger(Request $request): array
    {
        $q = PettyCashVoucher::with('creator')->latest();
        if ($request->filled('from'))   $q->whereDate('date_purchased', '>=', $request->from);
        if ($request->filled('to'))     $q->whereDate('date_purchased', '<=', $request->to);
        if ($request->filled('status')) $q->where('status', $request->status);
        if ($request->filled('officer')) $q->where('releasing_officer', 'like', '%' . $request->officer . '%');

        $rows = $q->get()->map(fn($v) => [
            $v->voucher_number,
            $v->date_purchased->format('Y-m-d'),
            $v->or_number,
            $v->store_name,
            $v->releasing_officer,
            $v->requested_amount,
            $v->total_amount,
            $v->change_amount,
            $v->status,
            $v->creator->name,
        ])->toArray();

        return [
            ['Voucher #', 'Date', 'OR #', 'Store', 'Releasing Officer', 'Requested', 'Spent', 'Change', 'Status', 'By'],
            $rows,
            'Voucher Ledger',
        ];
    }

    private function monthlySummary(Request $request): array
    {
        $year = $request->input('year', now()->year);

        $rows = PettyCashVoucher::selectRaw('MONTH(date_purchased) as month,
                SUM(requested_amount) as total_requested,
                SUM(total_amount) as total_spent,
                SUM(transport_fee) as total_transport,
                SUM(change_amount) as total_change,
                COUNT(*) as voucher_count')
            ->whereYear('date_purchased', $year)
            ->groupByRaw('MONTH(date_purchased)')
            ->orderByRaw('MONTH(date_purchased)')
            ->get()
            ->map(fn($r) => [
                date('F', mktime(0, 0, 0, $r->month, 1)),
                number_format($r->voucher_count),
                '₱' . number_format($r->total_requested, 2),
                '₱' . number_format($r->total_spent, 2),
                '₱' . number_format($r->total_transport, 2),
                '₱' . number_format($r->total_change, 2),
            ])->toArray();

        return [
            ['Month', 'Vouchers', 'Requested', 'Spent', 'Transport', 'Change Returned'],
            $rows,
            "Monthly Summary ($year)",
        ];
    }

    private function outstandingChanges(): array
    {
        $rows = PettyCashVoucher::whereIn('status', ['submitted', 'acknowledged'])
            ->where('change_amount', '>', 0)
            ->with('creator')
            ->latest()
            ->get()
            ->map(fn($v) => [
                $v->voucher_number,
                $v->date_purchased->format('Y-m-d'),
                $v->store_name,
                $v->releasing_officer,
                '₱' . number_format($v->change_amount, 2),
                $v->status,
                $v->creator->name,
            ])->toArray();

        return [
            ['Voucher #', 'Date', 'Store', 'Releasing Officer', 'Change Due', 'Status', 'Prepared By'],
            $rows,
            'Outstanding Changes',
        ];
    }

    private function itemPurchaseHistory(Request $request): array
    {
        $q = \App\Models\PettyCashItem::with('voucher')
            ->join('petty_cash_vouchers', 'petty_cash_items.petty_cash_voucher_id', '=', 'petty_cash_vouchers.id')
            ->orderByDesc('petty_cash_vouchers.date_purchased');

        if ($request->filled('item')) $q->where('petty_cash_items.item_name', 'like', '%' . $request->item . '%');
        if ($request->filled('from')) $q->whereDate('petty_cash_vouchers.date_purchased', '>=', $request->from);
        if ($request->filled('to'))   $q->whereDate('petty_cash_vouchers.date_purchased', '<=', $request->to);

        $rows = $q->select('petty_cash_items.*')->get()->map(fn($i) => [
            $i->voucher->date_purchased->format('Y-m-d'),
            $i->voucher->voucher_number,
            $i->item_name,
            $i->qty,
            $i->unit,
            '₱' . number_format($i->unit_cost, 2),
            '₱' . number_format($i->total_cost, 2),
            $i->voucher->store_name,
        ])->toArray();

        return [
            ['Date', 'Voucher #', 'Item', 'Qty', 'Unit', 'Unit Cost', 'Total', 'Store'],
            $rows,
            'Item Purchase History',
        ];
    }

    // ── CSV export ─────────────────────────────────────────────────────────

    private function csvResponse(array $headers, array $rows, string $filename): Response
    {
        $csv = implode(',', array_map(fn($h) => '"' . $h . '"', $headers)) . "\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v ?? '') . '"', $row)) . "\n";
        }

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '-' . now()->format('Ymd') . '.csv"',
        ]);
    }
}
```

- [ ] **Step 2: Create reports view**

```blade
{{-- resources/views/reports/index.blade.php --}}
<x-app-layout>
    <x-page-header title="Reports" subtitle="Inventory and petty cash audit reports." />

    {{-- Tab bar --}}
    <div class="flex gap-2 mb-6 border-b border-surface-border">
        @foreach([
            ['id' => 'inventory',   'label' => 'Inventory'],
            ['id' => 'petty-cash',  'label' => 'Petty Cash'],
        ] as $t)
            <a href="{{ $t['id'] === 'inventory' ? route('reports.inventory', 'received') : route('reports.petty-cash', 'ledger') }}"
               class="px-4 py-2 text-sm font-medium border-b-2 -mb-px transition
                      {{ ($tab ?? '') === $t['id']
                            ? 'border-primary-600 text-primary-700'
                            : 'border-transparent text-ink-muted hover:text-ink-heading' }}">
                {{ $t['label'] }}
            </a>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

        {{-- Report selector sidebar --}}
        <div class="space-y-1">
            @if(($tab ?? '') === 'inventory')
                @foreach([
                    ['type' => 'received',        'label' => 'Received Items'],
                    ['type' => 'released',         'label' => 'Released Items'],
                    ['type' => 'movement',         'label' => 'Stock Movement'],
                    ['type' => 'snapshot',         'label' => 'Current Stock Snapshot'],
                    ['type' => 'acknowledgement',  'label' => 'Acknowledgement Status'],
                ] as $r)
                    <a href="{{ route('reports.inventory', $r['type']) }}"
                       class="block px-3 py-2 rounded-lg text-sm transition
                              {{ ($type ?? '') === $r['type']
                                    ? 'bg-primary-50 text-primary-700 font-medium'
                                    : 'text-ink-body hover:bg-surface-page' }}">
                        {{ $r['label'] }}
                    </a>
                @endforeach
            @elseif(($tab ?? '') === 'petty-cash')
                @foreach([
                    ['type' => 'ledger',      'label' => 'Voucher Ledger'],
                    ['type' => 'monthly',     'label' => 'Monthly Summary'],
                    ['type' => 'outstanding', 'label' => 'Outstanding Changes'],
                    ['type' => 'purchases',   'label' => 'Item Purchase History'],
                ] as $r)
                    <a href="{{ route('reports.petty-cash', $r['type']) }}"
                       class="block px-3 py-2 rounded-lg text-sm transition
                              {{ ($type ?? '') === $r['type']
                                    ? 'bg-primary-50 text-primary-700 font-medium'
                                    : 'text-ink-body hover:bg-surface-page' }}">
                        {{ $r['label'] }}
                    </a>
                @endforeach
            @endif
        </div>

        {{-- Report content --}}
        <div class="lg:col-span-3">
            @if(isset($title))
                <x-bento-card>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-base font-semibold text-ink-heading">{{ $title }}</h2>
                        <a href="{{ request()->fullUrlWithQuery(['export' => '1']) }}"
                           class="text-xs text-primary-600 hover:underline flex items-center gap-1">
                            <x-heroicon-o-arrow-down-tray class="w-4 h-4"/> Export CSV
                        </a>
                    </div>

                    @if(empty($rows))
                        <x-empty-state icon="document-text" title="No data found"
                                       description="Try adjusting your filters." />
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-surface-border text-left text-xs text-ink-muted uppercase">
                                        @foreach($headers as $h)
                                            <th class="pb-2 px-2 font-semibold">{{ $h }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-border">
                                    @foreach($rows as $row)
                                        <tr>
                                            @foreach($row as $cell)
                                                <td class="py-2 px-2">{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-bento-card>
            @else
                <x-empty-state icon="chart-bar" title="Select a report"
                               description="Choose a report type from the left panel." />
            @endif
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/ReportController.php resources/views/reports/index.blade.php
git commit -m "feat(reports): ReportController with inventory and petty cash reports + CSV export"
```

---

## Task 13: UserController + user management views

**Files:**
- Create: `app/Http/Controllers/UserController.php`
- Create: `resources/views/users/index.blade.php`
- Create: `resources/views/users/create.blade.php`
- Create: `resources/views/users/edit.blade.php`

- [ ] **Step 1: Write UserManagementTest**

```php
<?php
// tests/Feature/UserManagementTest.php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_can_view_users_list(): void
    {
        $admin = $this->admin();
        User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($admin)->get('/users')->assertOk()->assertSee('staff');
    }

    public function test_admin_can_create_user(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/users', [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@lumc.local',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
            'role'                  => 'accounting',
        ])->assertRedirect('/users');

        $this->assertDatabaseHas('users', ['email' => 'jane@lumc.local', 'role' => 'accounting']);
    }

    public function test_admin_can_deactivate_user(): void
    {
        $admin  = $this->admin();
        $target = User::factory()->create(['role' => 'staff', 'is_active' => true]);

        $this->actingAs($admin)->patch("/users/{$target->id}/deactivate")
             ->assertRedirect('/users');

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
    }

    public function test_non_admin_cannot_create_user(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'is_active' => true]);
        $this->actingAs($staff)->post('/users', [])->assertForbidden();
    }
}
```

- [ ] **Step 2: Create UserController**

```php
<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::orderBy('name')->paginate(30);
        return view('users.index', compact('users'));
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'role'     => 'required|in:admin,staff,accounting',
        ]);

        User::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => Hash::make($data['password']),
            'role'      => $data['role'],
            'is_active' => true,
        ]);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email,' . $user->id,
            'role'     => 'required|in:admin,staff,accounting',
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user->update([
            'name'  => $data['name'],
            'email' => $data['email'],
            'role'  => $data['role'],
        ]);

        if (! empty($data['password'])) {
            $user->update(['password' => Hash::make($data['password'])]);
        }

        return redirect()->route('users.index')
            ->with('success', 'User updated.');
    }

    public function deactivate(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 422, 'You cannot deactivate your own account.');
        $user->update(['is_active' => false]);
        return redirect()->route('users.index')
            ->with('success', "{$user->name} has been deactivated.");
    }
}
```

- [ ] **Step 3: Create users/index view**

```blade
{{-- resources/views/users/index.blade.php --}}
<x-app-layout>
    <x-page-header title="User Management" subtitle="Manage system users and roles.">
        <x-slot name="actions">
            <x-button href="{{ route('users.create') }}" variant="primary">New User</x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')" />
    @endif

    <x-bento-card>
        <x-table>
            <x-slot name="head">
                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Email</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-ink-muted uppercase">Created</th>
                <th class="px-4 py-3"></th>
            </x-slot>
            @foreach($users as $u)
                <x-table.row>
                    <td class="px-4 py-3 font-medium text-ink-heading">{{ $u->name }}</td>
                    <td class="px-4 py-3 text-sm text-ink-muted">{{ $u->email }}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $u->role === 'admin' ? 'bg-primary-100 text-primary-800' :
                               ($u->role === 'accounting' ? 'bg-amber-100 text-amber-800' : 'bg-surface-page text-ink-muted') }}">
                            {{ ucfirst($u->role) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $u->is_active ? 'bg-teal-100 text-teal-800' : 'bg-rose-100 text-rose-700' }}">
                            {{ $u->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-ink-muted">{{ $u->created_at->format('M d, Y') }}</td>
                    <td class="px-4 py-3 text-right flex gap-3 justify-end">
                        <a href="{{ route('users.edit', $u) }}" class="text-xs text-primary-600 hover:underline">Edit</a>
                        @if($u->id !== auth()->id() && $u->is_active)
                            <form method="POST" action="{{ route('users.deactivate', $u) }}"
                                  onsubmit="return confirm('Deactivate {{ $u->name }}?')">
                                @csrf @method('PATCH')
                                <button type="submit" class="text-xs text-danger hover:underline">Deactivate</button>
                            </form>
                        @endif
                    </td>
                </x-table.row>
            @endforeach
        </x-table>
        <div class="px-4 py-3">{{ $users->links() }}</div>
    </x-bento-card>
</x-app-layout>
```

- [ ] **Step 4: Create users/create view**

```blade
{{-- resources/views/users/create.blade.php --}}
<x-app-layout>
    <x-page-header title="New User" subtitle="Create a system user account.">
        <x-slot name="actions">
            <x-button href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
        </x-slot>
    </x-page-header>

    <div class="max-w-lg">
        <x-bento-card>
            <form method="POST" action="{{ route('users.store') }}" class="space-y-4">
                @csrf

                <div>
                    <x-label for="name">Full Name</x-label>
                    <x-input id="name" name="name" value="{{ old('name') }}" required autofocus />
                    @error('name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="email">Email</x-label>
                    <x-input type="email" id="email" name="email" value="{{ old('email') }}" required />
                    @error('email') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="role">Role</x-label>
                    <x-select id="role" name="role" required>
                        <option value="">— Select Role —</option>
                        <option value="staff"      {{ old('role') === 'staff'      ? 'selected' : '' }}>Staff</option>
                        <option value="accounting" {{ old('role') === 'accounting' ? 'selected' : '' }}>Accounting</option>
                        <option value="admin"      {{ old('role') === 'admin'      ? 'selected' : '' }}>Admin</option>
                    </x-select>
                    @error('role') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="password">Password</x-label>
                    <x-input type="password" id="password" name="password" required />
                    @error('password') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="password_confirmation">Confirm Password</x-label>
                    <x-input type="password" id="password_confirmation" name="password_confirmation" required />
                </div>

                <x-button type="submit" variant="primary" class="w-full">Create User</x-button>
            </form>
        </x-bento-card>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Create users/edit view**

```blade
{{-- resources/views/users/edit.blade.php --}}
<x-app-layout>
    <x-page-header :title="'Edit: ' . $user->name" subtitle="Update user details and role.">
        <x-slot name="actions">
            <x-button href="{{ route('users.index') }}" variant="secondary">Cancel</x-button>
        </x-slot>
    </x-page-header>

    <div class="max-w-lg">
        <x-bento-card>
            <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-4">
                @csrf @method('PATCH')

                <div>
                    <x-label for="name">Full Name</x-label>
                    <x-input id="name" name="name" value="{{ old('name', $user->name) }}" required />
                    @error('name') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="email">Email</x-label>
                    <x-input type="email" id="email" name="email" value="{{ old('email', $user->email) }}" required />
                    @error('email') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="role">Role</x-label>
                    <x-select id="role" name="role" required>
                        @foreach(['staff', 'accounting', 'admin'] as $r)
                            <option value="{{ $r }}" {{ old('role', $user->role) === $r ? 'selected' : '' }}>
                                {{ ucfirst($r) }}
                            </option>
                        @endforeach
                    </x-select>
                    @error('role') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="password">New Password <span class="text-ink-muted">(leave blank to keep current)</span></x-label>
                    <x-input type="password" id="password" name="password" />
                    @error('password') <p class="text-xs text-danger mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="password_confirmation">Confirm New Password</x-label>
                    <x-input type="password" id="password_confirmation" name="password_confirmation" />
                </div>

                <x-button type="submit" variant="primary" class="w-full">Save Changes</x-button>
            </form>
        </x-bento-card>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Run all tests**

```bash
php artisan test tests/Feature/RoleMiddlewareTest.php tests/Feature/PettyCashVoucherTest.php tests/Feature/UserManagementTest.php
```

Expected: All tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/UserController.php \
        resources/views/users/ \
        tests/Feature/UserManagementTest.php
git commit -m "feat(users): UserController and admin user management views"
```

---

## Task 14: x-status-badge support for petty cash statuses

**Files:**
- Modify: `resources/views/components/status-badge.blade.php`

- [ ] **Step 1: Update status-badge to handle petty cash statuses**

Open `resources/views/components/status-badge.blade.php` and add these cases to the existing color map:

```blade
@php
$colors = [
    // existing inventory statuses
    'pending'      => 'bg-amber-100 text-amber-800',
    'acknowledged' => 'bg-teal-100 text-teal-800',
    'received'     => 'bg-primary-100 text-primary-800',
    'released'     => 'bg-purple-100 text-purple-800',
    // petty cash statuses
    'submitted'    => 'bg-blue-100 text-blue-800',
    'settled'      => 'bg-teal-100 text-teal-800',
];
$color = $colors[$status] ?? 'bg-surface-page text-ink-muted';
@endphp
<span {{ $attributes->class(["inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium $color"]) }}>
    {{ ucfirst($status) }}
</span>
```

- [ ] **Step 2: Run full test suite**

```bash
php artisan test
```

Expected: 27+ tests pass, ExampleTest still fails (pre-existing, unrelated).

- [ ] **Step 3: Commit**

```bash
git add resources/views/components/status-badge.blade.php
git commit -m "feat(status-badge): add petty cash status colors"
```

---

## Task 15: Final wiring and smoke test

- [ ] **Step 1: Assign roles to existing users via tinker**

```bash
php artisan tinker --execute="
App\Models\User::where('email', 'isolento92@gmail.com')->update(['role' => 'admin', 'is_active' => true]);
App\Models\User::where('email', 'jeremyfangon18@gmail.com')->update(['role' => 'staff', 'is_active' => true]);
echo 'Done';
"
```

- [ ] **Step 2: Run full test suite one final time**

```bash
php artisan test --stop-on-failure
```

Expected: All custom tests pass.

- [ ] **Step 3: Final commit**

```bash
git add -A
git status  # review what's staged
git commit -m "feat: petty cash, user roles, reports — complete implementation"
```

# Hospital-Wide Expansion — Plan B1: Departments Foundation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `departments` table and wire department-based scoping into every existing feature so all existing functionality works per-department.

**Architecture:** Single database, Option A — add `department_id` FK to users, items, transactions, and petty_cash_vouchers. Admin and accounting roles have NULL department_id (hospital-wide access). Staff are scoped to their assigned department. A `departmentScope()` helper on User drives all query filtering.

**Tech Stack:** Laravel 13, PHP 8.3, MySQL, Blade, Alpine.js, Tailwind CSS

---

## File Map

**New files:**
- `database/migrations/2026_05_29_000001_create_departments_table.php`
- `database/migrations/2026_05_29_000002_add_department_id_is_head_to_users_table.php`
- `database/migrations/2026_05_29_000003_add_department_fields_to_items_table.php`
- `database/migrations/2026_05_29_000004_add_department_id_to_transactions_table.php`
- `database/migrations/2026_05_29_000005_add_department_id_to_petty_cash_vouchers_table.php`
- `app/Models/Department.php`
- `app/Http/Controllers/DepartmentController.php`
- `resources/views/departments/index.blade.php`
- `resources/views/departments/create.blade.php`
- `resources/views/departments/edit.blade.php`
- `tests/Feature/DepartmentTest.php`

**Modified files:**
- `app/Models/User.php` — add `department()` relationship, `is_head` helper, `departmentScope()`, `canAccessReports()` update
- `app/Models/Item.php` — add `department()` relationship, expiry helpers, `isBelowMinStock()`
- `app/Models/Transaction.php` — add `department()` relationship, `department_id` to `$fillable`
- `app/Models/PettyCashVoucher.php` — add `department()` relationship, `department_id` to `$fillable`
- `app/Http/Controllers/Controller.php` — add `deptScope()` helper
- `app/Http/Controllers/ItemController.php` — scope by department
- `app/Http/Controllers/ReceiveController.php` — set department_id on create
- `app/Http/Controllers/ReleaseController.php` — scope items to dept
- `app/Http/Controllers/AcknowledgeController.php` — scope to dept
- `app/Http/Controllers/TransactionController.php` — scope to dept
- `app/Http/Controllers/DashboardController.php` — scope all stats to dept
- `app/Http/Controllers/ReportController.php` — scope reports to dept, open to all roles
- `app/Http/Controllers/UserController.php` — add department_id + is_head to CRUD
- `resources/views/layouts/app.blade.php` — show dept name in sidebar + add Departments nav
- `resources/views/users/create.blade.php` — add dept + is_head fields
- `resources/views/users/edit.blade.php` — add dept + is_head fields
- `routes/web.php` — add department routes, open reports to all roles

---

## Task 1: Create departments table migration

**Files:**
- Create: `database/migrations/2026_05_29_000001_create_departments_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php
// database/migrations/2026_05_29_000001_create_departments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();            // e.g. MIS, NURS, PHARM
            $table->string('responsibility_center_code')->nullable();
            $table->boolean('is_supply_hub')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default MIS department so existing data can be assigned to it
        DB::table('departments')->insert([
            'name'       => 'MIS Office',
            'code'       => 'MIS',
            'is_supply_hub' => false,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
```

- [ ] **Step 2: Do NOT run migrations yet** — run all 5 together in Task 6.

---

## Task 2: Add department_id + is_head to users

**Files:**
- Create: `database/migrations/2026_05_29_000002_add_department_id_is_head_to_users_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php
// database/migrations/2026_05_29_000002_add_department_id_is_head_to_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->after('is_active');
            $table->boolean('is_head')->default(false)->after('department_id');
        });

        // Assign all existing staff users to MIS department
        $misId = DB::table('departments')->where('code', 'MIS')->value('id');
        DB::table('users')->where('role', 'staff')->update(['department_id' => $misId]);
        // admin and accounting stay null (hospital-wide access)
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'is_head']);
        });
    }
};
```

---

## Task 3: Add department fields to items

**Files:**
- Create: `database/migrations/2026_05_29_000003_add_department_fields_to_items_table.php`

Note: `items` table already has a `category` column — we only add `department_id`, `expiry_date`, and `min_stock_qty`.

- [ ] **Step 1: Create the migration file**

```php
<?php
// database/migrations/2026_05_29_000003_add_department_fields_to_items_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->after('id');
            $table->date('expiry_date')->nullable()->after('category');
            $table->unsignedInteger('min_stock_qty')->default(0)->after('expiry_date');
        });

        // Assign all existing items to MIS department
        $misId = DB::table('departments')->where('code', 'MIS')->value('id');
        DB::table('items')->update(['department_id' => $misId]);
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['department_id', 'expiry_date', 'min_stock_qty']);
        });
    }
};
```

---

## Task 4: Add department_id to transactions

**Files:**
- Create: `database/migrations/2026_05_29_000004_add_department_id_to_transactions_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php
// database/migrations/2026_05_29_000004_add_department_id_to_transactions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->after('id');
        });

        $misId = DB::table('departments')->where('code', 'MIS')->value('id');
        DB::table('transactions')->update(['department_id' => $misId]);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
```

---

## Task 5: Add department_id to petty_cash_vouchers

**Files:**
- Create: `database/migrations/2026_05_29_000005_add_department_id_to_petty_cash_vouchers_table.php`

- [ ] **Step 1: Create the migration file**

```php
<?php
// database/migrations/2026_05_29_000005_add_department_id_to_petty_cash_vouchers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('petty_cash_vouchers', function (Blueprint $table) {
            $table->foreignId('department_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete()
                  ->after('id');
        });

        $misId = DB::table('departments')->where('code', 'MIS')->value('id');
        DB::table('petty_cash_vouchers')->update(['department_id' => $misId]);
    }

    public function down(): void
    {
        Schema::table('petty_cash_vouchers', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
```

---

## Task 6: Run all migrations

- [ ] **Step 1: Run migrations**

```bash
php artisan migrate
```

Expected output: 5 new migrations run, no errors.

- [ ] **Step 2: Verify in tinker**

```bash
php artisan tinker
```

```php
\App\Models\Department::all(['id','name','code']);
// Should show: [['id'=>1,'name'=>'MIS Office','code'=>'MIS']]

\Illuminate\Support\Facades\Schema::getColumnListing('users');
// Should include: department_id, is_head

\Illuminate\Support\Facades\Schema::getColumnListing('items');
// Should include: department_id, expiry_date, min_stock_qty
```

- [ ] **Step 3: Commit migrations**

```bash
git add database/migrations/2026_05_29_00000*
git commit -m "feat(departments): add departments table + department_id to all core tables"
```

---

## Task 7: Department model

**Files:**
- Create: `app/Models/Department.php`

- [ ] **Step 1: Create the model**

```php
<?php
// app/Models/Department.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'responsibility_center_code',
        'is_supply_hub',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_supply_hub' => 'boolean',
            'is_active'     => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function head(): HasOne
    {
        return $this->hasOne(User::class)->where('is_head', true);
    }

    /** Returns the Supply hub department, or null if not configured. */
    public static function supplyHub(): ?self
    {
        return static::where('is_supply_hub', true)->where('is_active', true)->first();
    }
}
```

---

## Task 8: Update User model

**Files:**
- Modify: `app/Models/User.php`

- [ ] **Step 1: Add department relationship and helpers**

Add to `$fillable`: `'department_id'`, `'is_head'`

Add to `casts()`: `'is_head' => 'boolean'`

Add these methods:

```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function department(): BelongsTo
{
    return $this->belongsTo(Department::class);
}

public function isHead(): bool
{
    return (bool) $this->is_head;
}

/**
 * Returns the department_id to use as a WHERE filter, or null if the user
 * can see all departments (admin / accounting).
 */
public function departmentScope(): ?int
{
    if (in_array($this->role, ['admin', 'accounting'])) {
        return null;
    }
    return $this->department_id;
}

// Update existing method — all authenticated users can see reports
// (scoped to their dept); admin/accounting see cross-dept reports
public function canAccessReports(): bool
{
    return true;
}

public function canAccessHospitalWideReports(): bool
{
    return in_array($this->role, ['admin', 'accounting']);
}
```

- [ ] **Step 2: Verify tinker**

```bash
php artisan tinker
```

```php
$u = \App\Models\User::first();
$u->departmentScope(); // null for admin, department_id for staff
$u->isHead();          // false
```

---

## Task 9: Update Item, Transaction, PettyCashVoucher models

**Files:**
- Modify: `app/Models/Item.php`
- Modify: `app/Models/Transaction.php`
- Modify: `app/Models/PettyCashVoucher.php`

- [ ] **Step 1: Update Item model**

Add to `$fillable`: `'department_id'`, `'expiry_date'`, `'min_stock_qty'`

Add to `casts()` (create the method if it doesn't exist):
```php
protected function casts(): array
{
    return [
        'expiry_date' => 'date',
    ];
}
```

Add these methods:
```php
use Illuminate\Database\Eloquent\Relations\BelongsTo;

public function department(): BelongsTo
{
    return $this->belongsTo(Department::class);
}

public function isExpired(): bool
{
    return $this->expiry_date !== null && $this->expiry_date->isPast();
}

public function isExpiringSoon(): bool
{
    return $this->expiry_date !== null
        && ! $this->expiry_date->isPast()
        && $this->expiry_date->diffInDays(now()) <= 30;
}

public function isBelowMinStock(): bool
{
    return $this->min_stock_qty > 0 && $this->current_qty <= $this->min_stock_qty;
}

/** Expiry badge: 'expired' | 'soon' | 'ok' | null */
public function expiryStatus(): ?string
{
    if ($this->expiry_date === null) return null;
    if ($this->isExpired())         return 'expired';
    if ($this->isExpiringSoon())    return 'soon';
    return 'ok';
}
```

- [ ] **Step 2: Update Transaction model**

Add to `$fillable`: `'department_id'`

Add method:
```php
public function department(): BelongsTo
{
    return $this->belongsTo(Department::class);
}
```

- [ ] **Step 3: Update PettyCashVoucher model**

Add to `$fillable`: `'department_id'`

Add method:
```php
public function department(): BelongsTo
{
    return $this->belongsTo(Department::class);
}
```

---

## Task 10: Add deptScope() to base Controller

**Files:**
- Modify: `app/Http/Controllers/Controller.php`

- [ ] **Step 1: Add the helper**

```php
<?php
// app/Http/Controllers/Controller.php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Returns the department_id to filter queries by, or null if the
     * authenticated user can see all departments (admin / accounting).
     */
    protected function deptScope(): ?int
    {
        return auth()->user()?->departmentScope();
    }
}
```

---

## Task 11: DepartmentController + routes

**Files:**
- Create: `app/Http/Controllers/DepartmentController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Create the controller**

```php
<?php
// app/Http/Controllers/DepartmentController.php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        $departments = Department::withCount('users')->orderBy('name')->get();
        return view('departments.index', compact('departments'));
    }

    public function create(): View
    {
        return view('departments.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'code'                       => ['required', 'string', 'max:20', 'unique:departments,code'],
            'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'is_supply_hub'              => ['boolean'],
        ]);

        // Only one supply hub allowed
        if (! empty($data['is_supply_hub'])) {
            Department::where('is_supply_hub', true)->update(['is_supply_hub' => false]);
        }

        Department::create($data + ['is_active' => true, 'is_supply_hub' => $request->boolean('is_supply_hub')]);

        return redirect()->route('departments.index')
            ->with('success', "Department \"{$data['name']}\" created.");
    }

    public function edit(Department $department): View
    {
        return view('departments.edit', compact('department'));
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $data = $request->validate([
            'name'                       => ['required', 'string', 'max:255'],
            'code'                       => ['required', 'string', 'max:20', Rule::unique('departments', 'code')->ignore($department->id)],
            'responsibility_center_code' => ['nullable', 'string', 'max:50'],
            'is_supply_hub'              => ['boolean'],
        ]);

        if ($request->boolean('is_supply_hub') && ! $department->is_supply_hub) {
            Department::where('is_supply_hub', true)->update(['is_supply_hub' => false]);
        }

        $department->update($data + ['is_supply_hub' => $request->boolean('is_supply_hub')]);

        return redirect()->route('departments.index')
            ->with('success', "Department \"{$department->name}\" updated.");
    }

    public function toggle(Department $department): RedirectResponse
    {
        $department->update(['is_active' => ! $department->is_active]);
        $action = $department->is_active ? 'activated' : 'deactivated';
        return back()->with('success', "\"{$department->name}\" {$action}.");
    }
}
```

- [ ] **Step 2: Add department routes to routes/web.php**

In the admin-only group, add after the users routes:

```php
// Departments (admin only)
Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create');
Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
Route::patch('/departments/{department}/toggle', [DepartmentController::class, 'toggle'])->name('departments.toggle');
```

Add the import at the top of routes/web.php:
```php
use App\Http\Controllers\DepartmentController;
```

Also move the reports routes to the `role:admin,staff,accounting` group (all roles can now access reports, scoped to their dept):
```php
// Reports — all roles (data scoped to their department)
Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
Route::get('/reports/inventory/{type}', [ReportController::class, 'inventory'])->name('reports.inventory');
Route::get('/reports/petty-cash/{type}', [ReportController::class, 'pettyCash'])->name('reports.petty-cash');
```

---

## Task 12: Department views

**Files:**
- Create: `resources/views/departments/index.blade.php`
- Create: `resources/views/departments/create.blade.php`
- Create: `resources/views/departments/edit.blade.php`

- [ ] **Step 1: Create departments/index.blade.php**

```blade
{{-- resources/views/departments/index.blade.php --}}
<x-app-layout>
    <x-page-header title="Departments" subtitle="Manage hospital departments and their access.">
        <x-slot name="actions">
            <x-button href="{{ route('departments.create') }}" variant="primary">
                <x-heroicon-o-plus class="w-4 h-4"/>
                New Department
            </x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <x-bento-card :padded="false">
        @if($departments->isEmpty())
            <x-empty-state icon="building-office" title="No departments yet" hint="Create the first department."/>
        @else
            <x-table :headers="['Name', 'Code', 'RC Code', 'Users', 'Type', 'Status', '']">
                @foreach($departments as $dept)
                    <x-table.row>
                        <td class="px-6 py-3 font-medium text-ink-heading">{{ $dept->name }}</td>
                        <td class="px-6 py-3 font-mono text-sm text-primary-700">{{ $dept->code }}</td>
                        <td class="px-6 py-3 text-ink-muted text-sm">{{ $dept->responsibility_center_code ?? '—' }}</td>
                        <td class="px-6 py-3 text-ink-body">{{ $dept->users_count }}</td>
                        <td class="px-6 py-3">
                            @if($dept->is_supply_hub)
                                <span class="inline-flex items-center bg-amber-50 text-amber-700 text-xs font-medium px-2.5 py-1 rounded-full">
                                    Supply Hub
                                </span>
                            @else
                                <span class="text-ink-muted text-sm">Department</span>
                            @endif
                        </td>
                        <td class="px-6 py-3">
                            @if($dept->is_active)
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-700">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-rose-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('departments.edit', $dept) }}" class="text-xs font-medium text-primary-600 hover:text-primary-700">Edit</a>
                                <form method="POST" action="{{ route('departments.toggle', $dept) }}">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="text-xs font-medium {{ $dept->is_active ? 'text-rose-500 hover:text-rose-700' : 'text-emerald-600 hover:text-emerald-700' }}">
                                        {{ $dept->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </x-table.row>
                @endforeach
            </x-table>
        @endif
    </x-bento-card>
</x-app-layout>
```

- [ ] **Step 2: Create departments/create.blade.php**

```blade
{{-- resources/views/departments/create.blade.php --}}
<x-app-layout>
    <x-page-header title="New Department" subtitle="Add a department to the hospital-wide system.">
        <x-slot name="actions">
            <x-button href="{{ route('departments.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    <div class="max-w-lg">
        <x-bento-card>
            <form method="POST" action="{{ route('departments.store') }}" class="space-y-5">
                @csrf

                <div>
                    <x-label for="name">Department Name</x-label>
                    <x-input id="name" name="name" :value="old('name')" placeholder="e.g. Nursing Department" required autofocus/>
                    @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="code">Department Code</x-label>
                    <x-input id="code" name="code" :value="old('code')" placeholder="e.g. NURS" required/>
                    <p class="mt-1 text-xs text-ink-muted">Short uppercase code used on RIS forms.</p>
                    @error('code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="responsibility_center_code">Responsibility Center Code</x-label>
                    <x-input id="responsibility_center_code" name="responsibility_center_code" :value="old('responsibility_center_code')" placeholder="Optional"/>
                    @error('responsibility_center_code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <input type="checkbox" id="is_supply_hub" name="is_supply_hub" value="1"
                           class="rounded border-surface-border text-primary-600"
                           {{ old('is_supply_hub') ? 'checked' : '' }}>
                    <label for="is_supply_hub" class="text-sm text-ink-body">
                        This is the <strong>Supply Hub</strong> (only one allowed hospital-wide)
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <x-button href="{{ route('departments.index') }}" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary">
                        <x-heroicon-o-building-office class="w-4 h-4"/>
                        Create Department
                    </x-button>
                </div>
            </form>
        </x-bento-card>
    </div>
</x-app-layout>
```

- [ ] **Step 3: Create departments/edit.blade.php**

```blade
{{-- resources/views/departments/edit.blade.php --}}
<x-app-layout>
    <x-page-header title="Edit Department" :subtitle="'Editing ' . $department->name">
        <x-slot name="actions">
            <x-button href="{{ route('departments.index') }}" variant="ghost">← Back</x-button>
        </x-slot>
    </x-page-header>

    @if(session('success'))
        <x-toast type="success" :message="session('success')"/>
    @endif

    <div class="max-w-lg">
        <x-bento-card>
            <form method="POST" action="{{ route('departments.update', $department) }}" class="space-y-5">
                @csrf @method('PATCH')

                <div>
                    <x-label for="name">Department Name</x-label>
                    <x-input id="name" name="name" :value="old('name', $department->name)" required autofocus/>
                    @error('name') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="code">Department Code</x-label>
                    <x-input id="code" name="code" :value="old('code', $department->code)" required/>
                    @error('code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div>
                    <x-label for="responsibility_center_code">Responsibility Center Code</x-label>
                    <x-input id="responsibility_center_code" name="responsibility_center_code" :value="old('responsibility_center_code', $department->responsibility_center_code)"/>
                    @error('responsibility_center_code') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-center gap-3 pt-1">
                    <input type="checkbox" id="is_supply_hub" name="is_supply_hub" value="1"
                           class="rounded border-surface-border text-primary-600"
                           {{ old('is_supply_hub', $department->is_supply_hub) ? 'checked' : '' }}>
                    <label for="is_supply_hub" class="text-sm text-ink-body">
                        This is the <strong>Supply Hub</strong>
                    </label>
                </div>

                <div class="flex justify-end gap-3 pt-1">
                    <x-button href="{{ route('departments.index') }}" variant="ghost">Cancel</x-button>
                    <x-button type="submit" variant="primary">
                        <x-heroicon-o-check class="w-4 h-4"/>
                        Save Changes
                    </x-button>
                </div>
            </form>
        </x-bento-card>
    </div>
</x-app-layout>
```

---

## Task 13: Update UserController + views for department assignment

**Files:**
- Modify: `app/Http/Controllers/UserController.php`
- Modify: `resources/views/users/create.blade.php`
- Modify: `resources/views/users/edit.blade.php`

- [ ] **Step 1: Update UserController — pass departments to views, validate dept**

In `create()`:
```php
public function create(): View
{
    $departments = \App\Models\Department::where('is_active', true)->orderBy('name')->get();
    return view('users.create', compact('departments'));
}
```

In `store()` — add department validation:
```php
'department_id' => ['nullable', 'exists:departments,id'],
'is_head'       => ['boolean'],
```

And in the `User::create()` call add:
```php
'department_id' => $data['department_id'] ?? null,
'is_head'       => $request->boolean('is_head'),
```

In `edit()`:
```php
public function edit(User $user): View
{
    $departments = \App\Models\Department::where('is_active', true)->orderBy('name')->get();
    return view('users.edit', compact('user', 'departments'));
}
```

In `update()` — add same validation + update:
```php
'department_id' => ['nullable', 'exists:departments,id'],
'is_head'       => ['boolean'],
```

```php
$user->department_id = $data['department_id'] ?? null;
$user->is_head       = $request->boolean('is_head');
```

- [ ] **Step 2: Add department + is_head fields to users/create.blade.php**

After the role `<x-select>` block, add:

```blade
<div>
    <x-label for="department_id">Department</x-label>
    <x-select id="department_id" name="department_id">
        <option value="">— No department (Admin / Accounting) —</option>
        @foreach($departments as $dept)
            <option value="{{ $dept->id }}" @selected(old('department_id') == $dept->id)>
                {{ $dept->name }} ({{ $dept->code }})
            </option>
        @endforeach
    </x-select>
    <p class="mt-1 text-xs text-ink-muted">Leave blank for Admin and Accounting roles.</p>
    @error('department_id') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
</div>

<div class="flex items-center gap-3">
    <input type="checkbox" id="is_head" name="is_head" value="1"
           class="rounded border-surface-border text-primary-600"
           {{ old('is_head') ? 'checked' : '' }}>
    <label for="is_head" class="text-sm text-ink-body">
        Designate as <strong>Department Head</strong> (can approve RIS and transfers)
    </label>
</div>
```

- [ ] **Step 3: Add same fields to users/edit.blade.php**

Same blocks as above but with old-value fallback:
```blade
<option value="{{ $dept->id }}" @selected(old('department_id', $user->department_id) == $dept->id)>
```

```blade
{{ old('is_head', $user->is_head) ? 'checked' : '' }}
```

---

## Task 14: Scope existing controllers by department

**Files:**
- Modify: `app/Http/Controllers/ItemController.php`
- Modify: `app/Http/Controllers/ReceiveController.php`
- Modify: `app/Http/Controllers/ReleaseController.php`
- Modify: `app/Http/Controllers/AcknowledgeController.php`
- Modify: `app/Http/Controllers/TransactionController.php`

- [ ] **Step 1: Scope ItemController**

In `index()`, change the query to:
```php
$scope = $this->deptScope();
$query = Item::when($scope, fn($q) => $q->where('department_id', $scope));
```

In `show()`, verify the item belongs to the user's dept (or user is admin/accounting):
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

- [ ] **Step 2: Scope ReceiveController**

In `store()`, set department_id on both item and transaction. Change item lookup to also filter by department:

```php
$deptId = auth()->user()->department_id;

$item = Item::where('name', $request->name)
    ->where('brand', $request->brand)
    ->where('department_id', $deptId)   // ← add this
    ->first();

if ($item) {
    $item->total_qty_received += $request->qty;
    $item->current_qty += $request->qty;
    $item->save();
} else {
    $item = Item::create([
        'name'            => $request->name,
        'category'        => $request->category,
        'brand'           => $request->brand,
        'model_number'    => $request->model_number,
        'serial_number'   => $request->serial_number,
        'unit'            => $request->unit ?? 'pcs',
        'total_qty_received' => $request->qty,
        'current_qty'     => $request->qty,
        'created_by'      => auth()->id(),
        'department_id'   => $deptId,     // ← add this
        'expiry_date'     => $request->expiry_date ?? null,
    ]);
}

Transaction::create([
    // ...existing fields...
    'department_id' => $deptId,           // ← add this
]);
```

- [ ] **Step 3: Scope ReleaseController**

In `index()`, scope items to department:
```php
$scope = $this->deptScope();
$items = Item::where('current_qty', '>', 0)
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->get();
```

In `store()`, set department_id on transaction:
```php
Transaction::create([
    // ...existing fields...
    'department_id' => auth()->user()->department_id,
]);
```

Also verify the item belongs to the user's dept before releasing:
```php
$scope = $this->deptScope();
if ($scope && $item->department_id !== $scope) {
    abort(403, 'You cannot release items from another department.');
}
```

- [ ] **Step 4: Scope AcknowledgeController**

In `index()` (get pending transactions):
```php
$scope = $this->deptScope();
$transactions = Transaction::where('type', 'released')
    ->where('acknowledgment_status', 'pending')
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->latest('date_released')
    ->paginate(20);
```

In `update()`, verify ownership:
```php
$scope = $this->deptScope();
if ($scope && $transaction->department_id !== $scope) {
    abort(403);
}
```

- [ ] **Step 5: Scope TransactionController**

In `index()`:
```php
$scope = $this->deptScope();
$transactions = Transaction::when($scope, fn($q) => $q->where('department_id', $scope))
    // ...rest of existing filters...
```

In `show()`:
```php
$scope = $this->deptScope();
if ($scope && $transaction->department_id !== $scope) {
    abort(403);
}
```

---

## Task 15: Scope DashboardController + ReportController

**Files:**
- Modify: `app/Http/Controllers/DashboardController.php`
- Modify: `app/Http/Controllers/ReportController.php`

- [ ] **Step 1: Scope DashboardController**

Add `$scope = auth()->user()->departmentScope();` at the top of `index()`.

Wrap every query that should be scoped:
```php
$scope = auth()->user()->departmentScope();

$totalInStock = Item::where('current_qty', '>', 0)
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->count();

$totalReleased = Transaction::where('type', 'released')
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->count();

$pendingAck = Transaction::where('type', 'released')
    ->where('acknowledgment_status', 'pending')
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->count();

$acknowledged = Transaction::where('type', 'released')
    ->where('acknowledgment_status', 'acknowledged')
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->count();

$pendingTransactions = Transaction::where('type', 'released')
    ->where('acknowledgment_status', 'pending')
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->latest()->limit(8)->get();

// weeklyActivity — scope releases
$weeklyActivity = collect(range(6, 0))->map(function ($daysAgo) use ($scope) {
    $date = Carbon::today()->subDays($daysAgo);
    return Transaction::where('type', 'released')
        ->whereDate('date_released', $date)
        ->when($scope, fn($q) => $q->where('department_id', $scope))
        ->count();
})->all();

// topOffice + topItem — scope to dept
$topOffice = Transaction::where('type', 'released')
    ->where('date_released', '>=', $startOfMonth)
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->selectRaw('released_to_office, COUNT(*) as c')
    ->groupBy('released_to_office')->orderByDesc('c')
    ->value('released_to_office');

// petty cash — scope to dept
$pcThisMonth = PettyCashVoucher::whereMonth('created_at', now()->month)
    ->whereYear('created_at', now()->year)
    ->whereIn('status', ['submitted', 'acknowledged', 'settled'])
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->sum('total_amount');

$pcVouchersThisMonth = PettyCashVoucher::whereMonth('created_at', now()->month)
    ->whereYear('created_at', now()->year)
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->count();

$recentVouchers = PettyCashVoucher::with('creator')
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->latest()->limit(5)->get();
```

- [ ] **Step 2: Scope ReportController**

Add `$scope = auth()->user()->departmentScope();` as a private helper:

```php
private function scope(): ?int
{
    return auth()->user()->departmentScope();
}
```

In every private report method, add `.when($scope, ...)` to the query. Example for `receivedItems()`:

```php
private function receivedItems(Request $request): array
{
    $scope = $this->scope();
    $q = Transaction::where('type', 'received')
        ->when($scope, fn($q) => $q->where('department_id', $scope))
        ->latest('date_received');
    // ...rest unchanged
}
```

Apply the same `.when($scope, fn($q) => $q->where('department_id', $scope))` pattern to all 9 report methods.

For petty cash reports that use `PettyCashVoucher`:
```php
$q = PettyCashVoucher::with('creator')
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->latest();
```

Also update `PettyCashController::store()` and `PettyCashController::index()` to set/scope by `department_id`:

In `store()`:
```php
$voucher = PettyCashVoucher::create([
    // ...existing fields...
    'department_id' => auth()->user()->department_id,
]);
```

In `index()`:
```php
$scope = $this->deptScope();
$vouchers = PettyCashVoucher::with('creator')
    ->when($scope, fn($q) => $q->where('department_id', $scope))
    ->latest()->paginate(20);
```

---

## Task 16: Update sidebar

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Show department name in sidebar footer**

In the sidebar footer section, add dept name below email:
```blade
<p class="text-sm font-medium text-ink-heading truncate">{{ auth()->user()->name }}</p>
<p class="text-xs text-ink-muted truncate">{{ auth()->user()->email }}</p>
@if(auth()->user()->department)
    <p class="text-xs text-primary-600 font-medium truncate mt-0.5">
        {{ auth()->user()->department->name }}
        @if(auth()->user()->is_head) · <span class="text-amber-600">Head</span> @endif
    </p>
@endif
```

- [ ] **Step 2: Add Departments nav item (admin only)**

After the Users nav link block, add:
```blade
{{-- Departments (admin only) --}}
@if($user->canManageUsers())
    @php $deptsActive = request()->is('departments*'); @endphp
    <a href="{{ route('departments.index') }}"
       class="relative flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
              {{ $deptsActive ? 'bg-primary-50 text-primary-700 font-medium' : 'text-ink-body hover:bg-surface-page hover:text-ink-heading' }}">
        @if($deptsActive)
            <span class="absolute left-0 top-1.5 bottom-1.5 w-1 bg-primary-600 rounded-r"></span>
        @endif
        <x-heroicon-o-building-office class="w-5 h-5 shrink-0"/>
        <span x-show="!collapsed" x-transition.opacity>Departments</span>
    </a>
@endif
```

- [ ] **Step 3: Update Reports nav — show for all roles**

Change the Reports nav link condition from `canAccessReports()` (unchanged since it now returns true for all) — the Reports link is already in the `@if($user->canAccessReports())` block. Since `canAccessReports()` now returns `true`, all users see the Reports link. No change needed.

---

## Task 17: Feature tests

**Files:**
- Create: `tests/Feature/DepartmentTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php
// tests/Feature/DepartmentTest.php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentTest extends TestCase
{
    use RefreshDatabase;

    private function makeDept(array $attrs = []): Department
    {
        return Department::create(array_merge([
            'name'      => 'Test Dept',
            'code'      => 'TEST',
            'is_active' => true,
        ], $attrs));
    }

    // ── Department CRUD (admin only) ──────────────────────────────────────

    public function test_admin_can_create_department(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('departments.store'), [
            'name' => 'Nursing Department',
            'code' => 'NURS',
        ])->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['code' => 'NURS']);
    }

    public function test_department_code_must_be_unique(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeDept(['code' => 'NURS']);

        $this->actingAs($admin)->post(route('departments.store'), [
            'name' => 'Another Nursing',
            'code' => 'NURS',
        ])->assertSessionHasErrors('code');
    }

    public function test_staff_cannot_access_departments(): void
    {
        $dept  = $this->makeDept();
        $staff = User::factory()->create(['role' => 'staff', 'department_id' => $dept->id]);

        $this->actingAs($staff)->get(route('departments.index'))->assertForbidden();
    }

    public function test_only_one_supply_hub_allowed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeDept(['code' => 'SUP1', 'is_supply_hub' => true]);

        // Creating a second supply hub should unset the first
        $this->actingAs($admin)->post(route('departments.store'), [
            'name'          => 'New Supply',
            'code'          => 'SUP2',
            'is_supply_hub' => '1',
        ])->assertRedirect();

        $this->assertEquals(1, Department::where('is_supply_hub', true)->count());
        $this->assertDatabaseHas('departments', ['code' => 'SUP2', 'is_supply_hub' => true]);
        $this->assertDatabaseHas('departments', ['code' => 'SUP1', 'is_supply_hub' => false]);
    }

    // ── Department scoping ────────────────────────────────────────────────

    public function test_staff_can_only_see_their_own_department_items(): void
    {
        $deptA = $this->makeDept(['code' => 'DEP-A']);
        $deptB = $this->makeDept(['name' => 'Dept B', 'code' => 'DEP-B']);

        $staff = User::factory()->create(['role' => 'staff', 'department_id' => $deptA->id]);

        $itemA = Item::factory()->create(['department_id' => $deptA->id, 'name' => 'Item A']);
        $itemB = Item::factory()->create(['department_id' => $deptB->id, 'name' => 'Item B']);

        $response = $this->actingAs($staff)->get(route('items.index'));
        $response->assertSee('Item A');
        $response->assertDontSee('Item B');
    }

    public function test_admin_sees_all_departments_items(): void
    {
        $deptA = $this->makeDept(['code' => 'DEP-A']);
        $deptB = $this->makeDept(['name' => 'Dept B', 'code' => 'DEP-B']);
        $admin = User::factory()->create(['role' => 'admin']);

        Item::factory()->create(['department_id' => $deptA->id, 'name' => 'Item A']);
        Item::factory()->create(['department_id' => $deptB->id, 'name' => 'Item B']);

        $response = $this->actingAs($admin)->get(route('items.index'));
        $response->assertSee('Item A');
        $response->assertSee('Item B');
    }

    public function test_staff_cannot_view_item_from_another_department(): void
    {
        $deptA = $this->makeDept(['code' => 'DEP-A']);
        $deptB = $this->makeDept(['name' => 'Dept B', 'code' => 'DEP-B']);
        $staff = User::factory()->create(['role' => 'staff', 'department_id' => $deptA->id]);
        $itemB = Item::factory()->create(['department_id' => $deptB->id]);

        $this->actingAs($staff)->get(route('items.show', $itemB))->assertForbidden();
    }

    // ── User department assignment ────────────────────────────────────────

    public function test_admin_can_assign_user_to_department(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dept  = $this->makeDept();
        $user  = User::factory()->create(['role' => 'staff']);

        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => 'staff',
            'department_id' => $dept->id,
        ])->assertRedirect(route('users.index'));

        $this->assertEquals($dept->id, $user->fresh()->department_id);
    }

    public function test_admin_can_designate_department_head(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dept  = $this->makeDept();
        $user  = User::factory()->create(['role' => 'staff', 'department_id' => $dept->id]);

        $this->actingAs($admin)->patch(route('users.update', $user), [
            'name'          => $user->name,
            'email'         => $user->email,
            'role'          => 'staff',
            'department_id' => $dept->id,
            'is_head'       => '1',
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->is_head);
    }
}
```

- [ ] **Step 2: Add Item factory**

Check if `ItemFactory` exists. If not, create `database/factories/ItemFactory.php`:

```php
<?php
// database/factories/ItemFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'               => fake()->words(3, true),
            'category'           => 'Office Supplies',
            'unit'               => 'pcs',
            'total_qty_received' => fake()->numberBetween(10, 100),
            'current_qty'        => fake()->numberBetween(1, 10),
            'department_id'      => null,
        ];
    }
}
```

- [ ] **Step 3: Run the tests**

```bash
php artisan test tests/Feature/DepartmentTest.php --verbose
```

Expected: All tests pass.

- [ ] **Step 4: Run the full suite to confirm no regressions**

```bash
php artisan test
```

Expected: All tests pass (50+ tests).

- [ ] **Step 5: Final commit**

```bash
git add .
git commit -m "feat(departments): B1 foundation — department scoping for all existing features"
```

---

## Self-Review Checklist

- [x] `departments` table + migration ✓
- [x] `department_id` + `is_head` on users ✓
- [x] `department_id` + `expiry_date` + `min_stock_qty` on items ✓
- [x] `department_id` on transactions + petty_cash_vouchers ✓
- [x] Department model + DepartmentController ✓
- [x] User model helpers (`departmentScope()`, `isHead()`) ✓
- [x] Item model helpers (`isExpired()`, `isExpiringSoon()`, `isBelowMinStock()`) ✓
- [x] All existing controllers scoped by department ✓
- [x] Sidebar shows dept name + head badge ✓
- [x] Departments nav item (admin only) ✓
- [x] Reports accessible to all roles (scoped to dept) ✓
- [x] Feature tests for scoping + CRUD ✓

---

## Next Plans

- **Plan B2:** RIS workflow — ris_requests, ris_items, full lifecycle, printable PAS-007-95 form
- **Plan B3:** Department transfers, Assembly, IAR, Attachments, Notifications, Reports

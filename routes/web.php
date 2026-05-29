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
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\RisController;
use App\Http\Controllers\RisHeadController;
use App\Http\Controllers\RisSupplyController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    // ── Staff + Admin: create/acknowledge vouchers ─────────────────────────
    // IMPORTANT: must be declared BEFORE the all-roles group so that
    // /petty-cash/create is matched before /petty-cash/{pettyCash}.
    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/petty-cash/create', [PettyCashController::class, 'create'])->name('petty-cash.create');
        Route::post('/petty-cash', [PettyCashController::class, 'store'])->name('petty-cash.store');
        Route::patch('/petty-cash/{pettyCash}/acknowledge', [PettyCashController::class, 'acknowledge'])->name('petty-cash.acknowledge');
    });

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

        // Petty cash — view for all roles (parameterized routes come after /create)
        Route::get('/petty-cash', [PettyCashController::class, 'index'])->name('petty-cash.index');
        Route::get('/petty-cash/{pettyCash}', [PettyCashController::class, 'show'])->name('petty-cash.show');
        Route::get('/petty-cash/{pettyCash}/print', [PettyCashController::class, 'print'])->name('petty-cash.print');

        // Reports — all roles (data scoped to their department)
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/inventory/{type}', [ReportController::class, 'inventory'])->name('reports.inventory');
        Route::get('/reports/petty-cash/{type}', [ReportController::class, 'pettyCash'])->name('reports.petty-cash');

        // RIS — read-only for accounting; full access handled per-controller
        Route::get('/ris', [RisController::class, 'index'])->name('ris.index');
        Route::get('/ris/{ris}', [RisController::class, 'show'])->name('ris.show');
        Route::get('/ris/{ris}/print', [RisController::class, 'print'])->name('ris.print');
    });

    // ── RIS — staff + admin (create / mutate) ─────────────────────────────
    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/ris/create', [RisController::class, 'create'])->name('ris.create');
        Route::post('/ris', [RisController::class, 'store'])->name('ris.store');
        Route::patch('/ris/{ris}/acknowledge', [RisController::class, 'acknowledge'])->name('ris.acknowledge');

        // Dept Head approval queue (authorizeHead() enforces is_head + dept match)
        Route::get('/ris-head', [RisHeadController::class, 'index'])->name('ris.head.index');
        Route::patch('/ris/{ris}/head-approve', [RisHeadController::class, 'approve'])->name('ris.head.approve');
        Route::patch('/ris/{ris}/head-reject', [RisHeadController::class, 'reject'])->name('ris.head.reject');

        // Supply queue (authorizeSupply() enforces supply-hub membership or admin)
        Route::get('/ris-supply', [RisSupplyController::class, 'index'])->name('ris.supply.index');
        Route::get('/ris/{ris}/supply-review', [RisSupplyController::class, 'review'])->name('ris.supply.review');
        Route::patch('/ris/{ris}/supply-issue', [RisSupplyController::class, 'issue'])->name('ris.supply.issue');
    });

    // ── Accounting + Admin: settle ────────────────────────────────────────
    Route::middleware('role:admin,accounting')->group(function () {
        Route::patch('/petty-cash/{pettyCash}/settle', [PettyCashController::class, 'settle'])->name('petty-cash.settle');
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

        // Departments (admin only)
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::get('/departments/create', [DepartmentController::class, 'create'])->name('departments.create');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::get('/departments/{department}/edit', [DepartmentController::class, 'edit'])->name('departments.edit');
        Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');
        Route::patch('/departments/{department}/toggle', [DepartmentController::class, 'toggle'])->name('departments.toggle');
    });
});

require __DIR__.'/auth.php';

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

        // Petty cash — view for all roles
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

    // ── Accounting + Admin: settle + reports ──────────────────────────────
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

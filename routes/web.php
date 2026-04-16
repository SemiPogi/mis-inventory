<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ReceiveController;
use App\Http\Controllers\ReleaseController;
use App\Http\Controllers\AcknowledgeController;
use App\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
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
});

require __DIR__.'/auth.php';
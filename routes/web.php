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
use App\Http\Controllers\TransferController;
use App\Http\Controllers\AssemblyController;
use App\Http\Controllers\IarController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ItemCategoryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {

    // ── Staff + Admin: /create routes MUST come before the all-roles group ───
    // IMPORTANT: parameterized routes like /{ris} and /{transfer} are declared
    // in the all-roles group below. Any /create GET must be declared here first
    // so Laravel doesn't swallow "create" as a model ID.
    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/petty-cash/create', [PettyCashController::class, 'create'])->name('petty-cash.create');
        Route::post('/petty-cash', [PettyCashController::class, 'store'])->name('petty-cash.store');
        Route::patch('/petty-cash/{pettyCash}/acknowledge', [PettyCashController::class, 'acknowledge'])->name('petty-cash.acknowledge');

        // RIS create — must be before /ris/{ris}
        Route::get('/ris/create', [RisController::class, 'create'])->name('ris.create');

        // Transfer create — must be before /transfers/{transfer}
        Route::get('/transfers/create', [TransferController::class, 'create'])->name('transfers.create');

        // Assembly create — must be before /assemblies/{assembly}
        Route::get('/assemblies/create', [AssemblyController::class, 'create'])->name('assemblies.create');
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
        // IMPORTANT: /ris/create must stay in the staff group below, but index/show/print are open to all
        Route::get('/ris', [RisController::class, 'index'])->name('ris.index');
        Route::get('/ris/{ris}', [RisController::class, 'show'])->name('ris.show');
        Route::get('/ris/{ris}/print', [RisController::class, 'print'])->name('ris.print');

        // Transfers — view for all (accounting can view; mutations in staff group)
        Route::get('/transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('/transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');

        // Assemblies — view for all
        Route::get('/assemblies', [AssemblyController::class, 'index'])->name('assemblies.index');
        Route::get('/assemblies/{assembly}', [AssemblyController::class, 'show'])->name('assemblies.show');

        // Notifications — all roles
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::get('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    });

    // ── RIS — staff + admin (create / mutate) ─────────────────────────────
    Route::middleware('role:admin,staff')->group(function () {
        // NOTE: /ris/create, /transfers/create, /assemblies/create are declared
        // at the top of the file to prevent route collision with /{model} params.
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

        // Transfers — mutate (head enforcement in controller)
        Route::post('/transfers', [TransferController::class, 'store'])->name('transfers.store');
        Route::get('/transfers-head', [TransferController::class, 'headQueue'])->name('transfers.head.index');
        Route::patch('/transfers/{transfer}/approve', [TransferController::class, 'approve'])->name('transfers.approve');
        Route::patch('/transfers/{transfer}/reject', [TransferController::class, 'reject'])->name('transfers.reject');
        Route::patch('/transfers/{transfer}/acknowledge', [TransferController::class, 'acknowledge'])->name('transfers.acknowledge');

        // Assemblies — store
        Route::post('/assemblies', [AssemblyController::class, 'store'])->name('assemblies.store');

        // IAR — supply only (controller enforces)
        Route::get('/iar', [IarController::class, 'index'])->name('iar.index');
        Route::get('/iar/create', [IarController::class, 'create'])->name('iar.create');
        Route::post('/iar', [IarController::class, 'store'])->name('iar.store');
        Route::get('/iar/{iar}', [IarController::class, 'show'])->name('iar.show');
        Route::patch('/iar/{iar}/accept', [IarController::class, 'accept'])->name('iar.accept');
        Route::patch('/iar/{iar}/reject', [IarController::class, 'reject'])->name('iar.reject');

        // Attachments
        Route::post('/attachments', [AttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('attachments.destroy');
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

        // Item Categories (admin only)
        Route::get('/item-categories', [ItemCategoryController::class, 'index'])->name('item-categories.index');
        Route::post('/item-categories', [ItemCategoryController::class, 'store'])->name('item-categories.store');
        Route::patch('/item-categories/{itemCategory}', [ItemCategoryController::class, 'update'])->name('item-categories.update');
        Route::patch('/item-categories/{itemCategory}/toggle', [ItemCategoryController::class, 'toggle'])->name('item-categories.toggle');
        Route::delete('/item-categories/{itemCategory}', [ItemCategoryController::class, 'destroy'])->name('item-categories.destroy');
    });
});

require __DIR__.'/auth.php';

<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\ProductionBatchController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

use App\Http\Controllers\SettingController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
})->name('login');

Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Setup Routes
Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
Route::post('/setup', [SetupController::class, 'process'])->name('setup.process');

Route::middleware('auth')->group(function () {
    Route::middleware('role:admin,manager,cashier')->group(function () {
        Route::get('/shift/open', [ShiftController::class, 'openForm'])->name('shift.open');
        Route::post('/shift/store', [ShiftController::class, 'store'])->name('shift.store');
        Route::get('/shift/close', [ShiftController::class, 'closeForm'])->name('shift.close');
        Route::post('/shift/close', [ShiftController::class, 'close'])->name('shift.close.store');
    });

    Route::get('/production', [ProductionBatchController::class, 'index'])->middleware('role:admin,manager')->name('batches.index');
    Route::post('/production', [ProductionBatchController::class, 'store'])->middleware('role:admin,manager')->name('batches.store');
    Route::post('/production/{batch}/wastage', [ProductionBatchController::class, 'storeWastage'])->middleware('role:admin,manager')->name('batches.wastage.store');
    Route::redirect('/kitchen', '/production');

    Route::middleware(['role:admin,manager,cashier', 'open.shift'])->group(function () {
        Route::get('/pos', [PosController::class, 'index'])->name('pos');
        Route::get('/dashboard', [PosController::class, 'index'])->name('dashboard');
        Route::post('/orders/store', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/api/customers/search', [CustomerController::class, 'search'])->name('customers.search');
        Route::post('/api/orders/{id}/cancel', [OrderController::class, 'cancel'])->middleware('role:admin,manager')->name('orders.cancel');
        Route::get('/menu', [PosController::class, 'menu'])->middleware('role:admin,manager')->name('menu.index');
        Route::post('/menu/categories', [PosController::class, 'storeCategory'])->middleware('role:admin,manager')->name('categories.store');
        Route::post('/menu/items', [PosController::class, 'storeItem'])->middleware('role:admin,manager')->name('items.store');
        Route::post('/menu/items/{item}/loss', [PosController::class, 'storeStockLoss'])->middleware('role:admin,manager')->name('items.loss.store');
        Route::post('/menu/items/import', [PosController::class, 'importItems'])->middleware('role:admin,manager')->name('items.import');
        Route::get('/menu/items/template', [PosController::class, 'catalogTemplate'])->middleware('role:admin,manager')->name('items.template');
        Route::get('/reports', [PosController::class, 'reports'])->middleware('role:admin,manager')->name('reports.index');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/settings', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/settings/update', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/backup', [SettingController::class, 'backup'])->name('settings.backup');
        Route::post('/settings/test-printer', [SettingController::class, 'testPrinter'])->name('settings.test-printer');
        Route::post('/settings/test-cash-drawer', [SettingController::class, 'testCashDrawer'])->name('settings.test-cash-drawer');
        Route::post('/settings/users', [SettingController::class, 'storeUser'])->name('users.store');
        Route::delete('/settings/users/{id}', [SettingController::class, 'deleteUser'])->name('users.delete');
    });
    Route::post('/settings/reset', [SettingController::class, 'reset'])->middleware('role:admin')->name('system.reset');
});

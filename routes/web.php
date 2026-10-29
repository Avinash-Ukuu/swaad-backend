<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\{AuthController, OrderController};
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::middleware(['auth', 'admin'])->group(function () {
        Route::get('/', [OrderController::class, 'dashboard'])->name('dashboard');
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::get('/orders/{order}/invoice', [OrderController::class, 'downloadInvoice'])->name('orders.invoice');
        Route::patch('/orders/{order}', [OrderController::class, 'update'])->name('orders.update');
    });
});

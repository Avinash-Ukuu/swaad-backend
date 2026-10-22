<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{CheckoutController, PaymentController, AddressController};


Route::post('/checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1');
Route::post('/payments/verify', [PaymentController::class, 'verify'])->middleware('throttle:20,1');
Route::post('/payments/webhook', [PaymentController::class, 'webhook'])->name('razorpay.webhook');
Route::get('/orders/{orderNumber}/status', [PaymentController::class, 'status'])->middleware('throttle:30,1');
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/addresses', [AddressController::class, 'index']);
//     Route::post('/addresses', [AddressController::class, 'store']);
//     Route::put('/addresses/{address}', [AddressController::class, 'update']);
//     Route::delete('/addresses/{address}', [AddressController::class, 'destroy']);
// });

<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\ValidatePaymentController;
use App\Http\Controllers\WebhookController;
use App\Http\Middleware\VerifyWebhookSignature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:business');

Route::post('/payments', [PaymentController::class, 'store'])->middleware('auth:business');
Route::post('/payments/{reference}/authorize', [PaymentController::class, 'update'])->middleware('auth:business');
Route::post('/payments/{reference}/validate', ValidatePaymentController::class)->middleware('auth:business');
Route::get('/transactions/{reference}', [TransactionController::class, 'show'])->middleware('auth:business');

Route::post('/webhooks/{provider:identifier}', WebhookController::class)
//    ->middleware(VerifyWebhookSignature::class)
    ->name('webhooks');

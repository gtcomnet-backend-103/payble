<?php

declare(strict_types=1);

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\TransferController;
use App\Http\Controllers\Api\ValidatePaymentController;
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

Route::group(['prefix' => 'utils', 'middleware' => 'auth:business'], function () {
    Route::get('/banks', [TransferController::class, 'banks']);
});

Route::group(['middleware' => 'auth:business'], function () {
    Route::post('/recipients', [TransferController::class, 'registerRecipient']);
    Route::post('/transfers', [TransferController::class, 'initiate']);
    Route::post('/transfers/{reference}/authorize', [TransferController::class, 'authorize']);
});

Route::post('/webhooks/{provider:identifier}', App\Http\Controllers\Support\WebhookController::class)
    ->middleware(VerifyWebhookSignature::class)
    ->name('webhooks');

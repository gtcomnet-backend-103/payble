<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\PaymentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/payments', [PaymentController::class, 'store'])->middleware('auth:sanctum');
Route::post('/payments/{reference}/authorize', [PaymentController::class, 'update'])->middleware('auth:sanctum');

Route::post('/webhooks/{provider}', WebhookController::class)->name('webhooks');

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TransactionController
{
    public function show(string $reference, Request $request): TransactionResource|JsonResponse
    {
        $actor = $request->user();
        $business = $actor instanceof \App\Models\Business ? $actor : $actor->businesses()->first();

        if (! $business) {
            return response()->json([
                'message' => 'Active business not found.',
            ], 403);
        }

        $transaction = Transaction::query()
            ->where('business_id', $business->id)
            ->where('reference', $reference)
            ->first();

        if (! $transaction) {
            return response()->json([
                'message' => 'Transaction not found.',
            ], 404);
        }

        $transaction->load(['paymentIntent.customer']);

        return new TransactionResource($transaction);
    }
}

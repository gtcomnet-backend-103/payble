<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Payments\Actions\AuthorizePayment;
use App\Domains\Payments\Actions\RequestPayment;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Http\Requests\AuthorizePaymentRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Resources\TransactionResource;
use Exception;
use Illuminate\Http\JsonResponse;

final class PaymentController
{
    public function __construct(
        private RequestPayment $requestPayment,
        private AuthorizePayment $authorizePayment
    ) {}

    public function store(StorePaymentRequest $request): TransactionResource|JsonResponse
    {
        $actor = $request->user();
        $business = $actor instanceof \App\Models\Business ? $actor : $actor->businesses()->first();

        if (! $business) {
            return response()->json([
                'message' => 'Active business not found.',
            ], 403);
        }

        $transaction = $this->requestPayment->execute($business, $request->validated());

        return new TransactionResource($transaction);
    }

    public function update(string $reference, AuthorizePaymentRequest $request): JsonResponse
    {
        try {
            $channel = PaymentChannel::from($request->validated('channel') ?? PaymentChannel::Card->value);
            $attempt = $this->authorizePayment->execute($reference, $channel, $request->validated());
            $attempt->load(['paymentIntent.customer']);
            $payment = $attempt->paymentIntent;

            if (in_array($attempt->status, [AuthorizationStatus::PendingPin, AuthorizationStatus::PendingOtp])) {
                return response()->json([
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                    'action' => $attempt->action,
                ]);
            }

            return response()->json([
                'status' => $attempt->status->value,
                'amount' => $payment->amount,
                'reference' => $payment->reference,
                'customer' => [
                    'first_name' => $payment->customer->first_name,
                    'last_name' => $payment->customer->last_name,
                    'email' => $payment->customer->email,
                    'phone' => $payment->customer->phone,
                ],
                'fee' => $attempt->fee,
                'authorization' => $attempt->authorization,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

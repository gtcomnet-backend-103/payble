<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domains\Payments\Actions\ValidatePayment;
use App\Http\Requests\ValidatePaymentRequest;
use Exception;
use Illuminate\Http\JsonResponse;

final class ValidatePaymentController
{
    public function __construct(
        private ValidatePayment $validatePayment
    ) {}

    public function __invoke(string $reference, ValidatePaymentRequest $request): JsonResponse
    {
        try {
            $attempt = $this->validatePayment->execute($reference, $request->validated());
            $attempt->load(['intent.customer']);
            $payment = $attempt->Intent;

            if ($attempt->status->validating()) {
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
            report($e);
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

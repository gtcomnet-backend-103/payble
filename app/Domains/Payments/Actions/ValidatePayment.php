<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Contracts\IdempotentAction;
use App\Enums\AuthorizationStatus;
use App\Models\AuthorizationAttempt;
use App\Models\PaymentIntent;
use App\Supports\Providers\DataTransferObjects\PaymentValidateDTO;
use App\Supports\Providers\Facades\PaymentProvider;
use App\Supports\Services\TransactionAttemptService;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ValidatePayment implements IdempotentAction
{
    public function __construct(
        private readonly ProcessPaymentAttempt $processPaymentAttempt,
        private TransactionAttemptService $transactionAttemptService,
    ) {}

    /**
     * @throws Exception|Throwable
     */
    public function execute(string $reference, array $data): AuthorizationAttempt
    {
        return DB::transaction(function () use ($reference, $data) {
            // 1. Resolve and lock the Payment
            $payment = PaymentIntent::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new Exception("Payment not found for reference: {$reference}", 404);
            }

            // 2. Preconditions
            if ($payment->status->isFinal()) {
                throw new Exception('Payment has already been authorized.', 400);
            }

            // 3. Create a NEW attempt for auditability of this validation step
            // We copy provider details from the latest attempt.
            $attempt = $this->transactionAttemptService->continueAttempt($payment);

            // 4. Call Provider Validate
            $providerResponse = PaymentProvider::validate(
                $attempt->provider,
                $attempt->provider_reference,
                match ($attempt->status) {
                    AuthorizationStatus::PendingPin => new PaymentValidateDTO(pin: $data['pin'] ?? null),
                    AuthorizationStatus::PendingOtp => new PaymentValidateDTO(otp: $data['otp'] ?? null),
                    AuthorizationStatus::PendingPhone => new PaymentValidateDTO(phone: $data['phone'] ?? null),
                    default => new PaymentValidateDTO(),
                }
            );

            // 5. Update the NEW attempt with result
            $rawResponse = array_merge($providerResponse->rawResponse, [
                'bank_details' => $providerResponse->bankDetails?->toArray(),
            ]);
            $metadata = array_merge($attempt->metadata ?? [], $providerResponse->metadata);
            $attempt = $this->transactionAttemptService->saveAttemptResponse($attempt, $providerResponse->status, $rawResponse, $metadata);


            // 6. If success, finalize the payment
            if ($attempt->status->is(AuthorizationStatus::Success)) {
                $this->processPaymentAttempt->execute($attempt);
            }

            return $attempt;
        });
    }
}

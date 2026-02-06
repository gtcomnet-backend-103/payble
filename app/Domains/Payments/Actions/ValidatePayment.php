<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Payments\Providers\DataTransferObjects\PaymentValidateDTO;
use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use App\Models\PaymentIntent;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ValidatePayment
{
    public function __construct(
        private ProcessPaymentAttempt $processPaymentAttempt
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
            if ($payment->status->is(PaymentStatus::Success)) {
                throw new Exception('Payment has already been successful.', 400);
            }

            // 3. Find latest pending attempt
            // We need the *latest* one to get the provider reference and context.
            // It might be Pending, PendingOtp, PendingPin, etc.
            $latestAttempt = AuthorizationAttempt::where('payment_intent_id', $payment->id)
                ->pending()
                ->latest()
                ->first();

            if (! $latestAttempt) {
                throw new Exception('No pending authorization attempt found.', 400);
            }

            // 4. Create a NEW attempt for auditability of this validation step
            // We copy provider details from the latest attempt.
            $newAttempt = AuthorizationAttempt::create([
                'provider_reference' => $latestAttempt->provider_reference, // Use same provider ref
                'payment_intent_id' => $payment->id,
                'provider_id' => $latestAttempt->provider_id,
                'channel' => $latestAttempt->channel,
                'status' => AuthorizationStatus::Pending, // Start as pending
                'fee' => $latestAttempt->fee,
                'provider_fee' => $latestAttempt->provider_fee,
                'amount' => $latestAttempt->amount,
                'currency' => $latestAttempt->currency,
                'idempotency_key' => "payment_validate_{$payment->id}_".Str::uuid(),
                'metadata' => array_merge($latestAttempt->metadata ?? [], ['validation_step' => true]),
            ]);

            // 5. Call Provider Validate
            $providerResponse = PaymentProvider::validate(
                $newAttempt->provider,
                $newAttempt->provider_reference,
                new PaymentValidateDTO(
                    pin: $data['pin'] ?? null,
                    otp: $data['otp'] ?? null,
                    phone: $data['phone'] ?? null,
                    birthday: $data['birthday'] ?? null,
                    address: $data['address'] ?? null,
                )
            );

            // 6. Update the NEW attempt with result
            $newAttempt->update([
                'status' => $providerResponse->status,
                'raw_response' => array_merge($providerResponse->rawResponse, [
                    'bank_details' => $providerResponse->bankDetails?->toArray(),
                ]),
                'metadata' => array_merge($newAttempt->metadata ?? [], $providerResponse->metadata),
            ]);

            // 7. If success, finalize the payment
            if ($newAttempt->status->is(AuthorizationStatus::Success)) {
                $this->processPaymentAttempt->execute($newAttempt);
            }

            return $newAttempt;
        });
    }
}

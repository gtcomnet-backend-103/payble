<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Providers\DataTransferObjects\CustomerDTO;
use App\Domains\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Providers\Facades\PaymentProvider;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use App\Models\PaymentIntent;
use Exception;
use Illuminate\Support\Facades\DB;

final class AuthorizePayment
{
    public function __construct(
        private SelectProvider $selectProvider,
        private ResolvePaymentFee $resolvePaymentFee,
    ) {}

    /**
     * Authorize a payment intent.
     *
     * @throws Exception
     */
    public function execute(string $reference, PaymentChannel $channel): AuthorizationAttempt
    {
        return DB::transaction(function () use ($reference, $channel) {
            // 1. Resolve and lock the Payment record
            $payment = PaymentIntent::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (! $payment) {
                throw new Exception("Payment not found for reference: {$reference}", 404);
            }

            // 2. Validate Preconditions
            $this->validatePreconditions($payment, $channel);

            // 3. Handle Idempotency
            $existingAttempt = AuthorizationAttempt::where('payment_intent_id', $payment->id)
                ->where('channel', $channel)
                ->first();

            if ($existingAttempt) {
                return $existingAttempt;
            }

            // 4. Select provider and resolve fee
            $provider = $this->selectProvider->execute($channel);
            $feeAmount = $this->resolvePaymentFee->execute($payment, $channel);

            // 5. Create AuthorizationAttempt record
            $attempt = AuthorizationAttempt::create([
                'payment_intent_id' => $payment->id,
                'provider_id' => $provider->id,
                'channel' => $channel,
                'status' => AuthorizationStatus::Pending,
                'fee_amount' => $feeAmount,
                'currency' => $payment->currency->value,
                'idempotency_key' => "auth_{$payment->id}_{$channel->value}",
            ]);

            // 6. Call provider authorization API via Facade
            $dto = new PaymentAuthorizeDTO(
                reference: $payment->reference,
                amount: $payment->amount,
                currency: $payment->currency,
                channel: $channel,
                customer: new CustomerDTO(
                    $payment->customer->first_name,
                    $payment->customer->last_name,
                    $payment->customer->email,
                    $payment->customer->phone,
                ),
                metadata: $payment->metadata ?? []
            );

            $providerResponse = PaymentProvider::authorize($provider, $dto);

            // 7. Persist adapter response and transition state
            $attempt->update([
                'provider_reference' => $providerResponse->providerReference,
                'status' => $providerResponse->status,
                'raw_response' => array_merge($providerResponse->rawResponse, [
                    'bank_details' => $providerResponse->bankDetails?->toArray(),
                ]),
                'metadata' => array_merge($attempt->metadata ?? [], $providerResponse->metadata),
            ]);

            return $attempt;
        });
    }

    /**
     * @throws Exception
     */
    public function validatePreconditions(PaymentIntent $payment, PaymentChannel $channel): void
    {
        if (! PaymentStatus::Initiated->is($payment->status)) {
            throw new Exception('Payment has already been successful.', 400);
        }
    }
}

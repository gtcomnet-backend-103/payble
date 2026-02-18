<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Contracts\IdempotentAction;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use App\Models\PaymentIntent;
use App\Supports\Providers\DataTransferObjects\CustomerDTO;
use App\Supports\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Supports\Providers\Facades\PaymentProvider;
use App\Supports\Services\TransactionAttemptService;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class AuthorizePayment implements IdempotentAction
{
    public function __construct(
        private ProcessPaymentAttempt $processPaymentAttempt,
        private TransactionAttemptService $attemptService
    ) {}

    /**
     * Authorize a payment intent.
     *
     * @throws Throwable
     */
    public function execute(string $reference, PaymentChannel $channel, array $data = []): AuthorizationAttempt
    {
        return DB::transaction(function () use ($reference, $channel, $data) {
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
            $existingAttempt = AuthorizationAttempt::where('intent_id', $payment->getKey())
                ->where('intent_type', $payment->getMorphClass())
                ->where('channel', $channel)
                ->first();

            if ($existingAttempt) {
                throw new Exception("Payment already authorized for reference: {$reference}");
            }

            // 4. Select provider and resolve fee
            $provider = PaymentProvider::provide($channel->value);

            // 5. Create AuthorizationAttempt record
            $attempt = $this->createAttempt($payment, $channel);

            // 6. Call provider authorization API via Facade
            $dto = new PaymentAuthorizeDTO(
                reference: $attempt->provider_reference,
                amount: $payment->amount,
                currency: $payment->currency,
                channel: $channel,
                customer: new CustomerDTO(
                    firstName: $payment->customer->first_name,
                    lastName: $payment->customer->last_name,
                    email: $payment->customer->email,
                    phone: $payment->customer->phone
                ),
                metadata: $payment->metadata ?? [], // Keep metadata for actual custom data
                channelDetails: match ($channel) {
                    PaymentChannel::Card => $data['card'] ?? [],
                    default => []
                }
            );

            $providerResponse = PaymentProvider::authorize($provider, $dto);

            // 7. Persist adapter response and transition state
            $rawResponse = array_merge($providerResponse->rawResponse, [
                'bank_details' => $providerResponse->bankDetails?->toArray(),
            ]);
            $metadata = array_merge($attempt->metadata ?? [], $providerResponse->metadata);
            $attempt = $this->attemptService->saveAttemptResponse($attempt, $providerResponse->status, $rawResponse, $metadata);

            if ($attempt->status->isFinal()) {
                $this->processPaymentAttempt->execute($attempt);
            }

            return $attempt;
        });
    }

    /**
     * @throws Exception
     */
    public function validatePreconditions(PaymentIntent $payment, PaymentChannel $channel): void
    {
        if (! PaymentStatus::Initiated->is($payment->status)) {
            throw new Exception('Payment has already been authorized.', 400);
        }
    }

    /**
     * @throws Exception
     */
    public function createAttempt(PaymentIntent $payment, PaymentChannel $channel): AuthorizationAttempt
    {
        try {
            $provider = PaymentProvider::provide($channel->value);
            return $this->attemptService->createAttempt(
                $payment->amount,
                $payment->currency,
                $channel->toFeeChannelEnum(),
                $payment,
                $provider
            );
        } catch (Exception $exception) {
            report($exception);
            throw new Exception('failed to attempt authorization');
        }
    }
}

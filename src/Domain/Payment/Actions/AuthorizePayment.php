<?php

declare(strict_types=1);

namespace Domain\Payment\Actions;

use App\Enums\AuthorizationStatus;
use App\Enums\FeeBearer;
use Domain\Payment\Contracts\AuthorizationRepositoryInterface;
use Domain\Payment\Contracts\FeeResolverInterface;
use Domain\Payment\Contracts\PaymentGatewayInterface;
use Domain\Payment\Contracts\ProviderSelectorInterface;
use Domain\Payment\DataTransferObjects\AuthorizationData;
use Domain\Payment\Entities\AuthorizationAttempt;
use Domain\Payment\Entities\PaymentRequest;
use Exception;
use Support\ValueObjects\Ulid;

final readonly class AuthorizePayment
{
    public function __construct(
        private AuthorizationRepositoryInterface $repository,
        private ProviderSelectorInterface $providerSelector,
        private FeeResolverInterface $feeResolver,
        private PaymentGatewayInterface $gateway,
    ) {}

    /**
     * @throws Exception
     */
    public function execute(AuthorizationData $data): AuthorizationAttempt
    {
        // 1. Resolve and lock the Payment record (Repository handles locking)
        $payment = $this->repository->lockPaymentRequest($data->reference);

        if (! $payment) {
            throw new Exception("Payment not found for reference: {$data->reference}", 404);
        }

        // 2. Validate Preconditions
        $this->ensureCanBeAuthorized($payment);

        // 3. Handle Idempotency
        $existingAttempt = $this->repository->findExistingAttempt($payment->id->toString(), $data->channel->value);

        if ($existingAttempt) {
            throw new Exception("Payment already authorized for reference: {$data->reference}");
        }

        // 4. Select provider and resolve fee
        $providerId = $this->providerSelector->select($data->channel);
        $feeAmount = $this->feeResolver->resolve($payment, $data->channel);

        // 5. Calculate total amount based on bearer
        $amount = match ($payment->bearer) {
            FeeBearer::Customer => $payment->amount->value + $feeAmount,
            FeeBearer::Merchant => $payment->amount->value,
        };

        // 6. Get Provider Fee
        $providerFee = $this->gateway->getProviderFee($providerId, $data->channel, $amount);

        // 7. Create AuthorizationAttempt entity
        $attempt = new AuthorizationAttempt(
            id: Ulid::generate(),
            providerReference: \Illuminate\Support\Str::uuid()->toString(),
            paymentIntentId: $payment->id->toString(),
            providerId: $providerId,
            channel: \App\Enums\PaymentChannel::from($data->channel->value),
            status: AuthorizationStatus::Pending,
            amount: $amount,
            currency: $payment->amount->currency->value,
            fee: $feeAmount,
            providerFee: $providerFee,
            idempotencyKey: "payment_auth_{$payment->id->toString()}_{$data->channel->value}",
            metadata: $data->metadata,
        );

        $this->repository->save($attempt);

        // 8. Call provider authorization API
        $response = $this->gateway->authorize($providerId, [
            'reference' => $attempt->providerReference,
            'amount' => $payment->amount->value,
            'currency' => $payment->amount->currency->value,
            'channel' => $data->channel,
            'customer' => [
                'firstName' => $payment->customer->firstName,
                'lastName' => $payment->customer->lastName,
                'email' => $payment->customer->email,
                'phone' => $payment->customer->phone,
            ],
            'card' => $data->card,
            'metadata' => $payment->getMetadata(),
        ]);

        // 9. Process Response
        $updatedAttempt = new AuthorizationAttempt(
            id: $attempt->id,
            providerReference: $response['provider_reference'] ?? $attempt->providerReference,
            paymentIntentId: $attempt->paymentIntentId,
            providerId: $attempt->providerId,
            channel: $attempt->channel,
            status: AuthorizationStatus::from($response['status']),
            amount: $attempt->amount,
            currency: $attempt->currency,
            fee: $attempt->fee,
            providerFee: $attempt->providerFee,
            idempotencyKey: $attempt->idempotencyKey,
            action: $response['action'] ?? null,
            rawResponse: $response['raw_response'] ?? [],
            metadata: array_merge($attempt->metadata ?? [], $response['metadata'] ?? []),
        );

        $this->repository->save($updatedAttempt);

        return $updatedAttempt;
    }

    /**
     * @throws Exception
     */
    private function ensureCanBeAuthorized(PaymentRequest $payment): void
    {
        if (! $payment->getStatus()->canBeAuthorized()) {
            throw new Exception('Payment has already been authorized.', 400);
        }
    }
}

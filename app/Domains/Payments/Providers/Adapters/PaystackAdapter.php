<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Adapters;

use App\Domains\Payments\Providers\Contracts\ProviderAdapter;
use App\Domains\Payments\Providers\DataTransferObjects\BankDetailsDTO;
use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use Illuminate\Support\Str;

final class PaystackAdapter implements ProviderAdapter
{
    public function authorize(PaymentAuthorizeDTO $dto): ProviderResponse
    {
        // Mock logic for now
        if ($dto->channel === PaymentChannel::BankTransfer) {
            return new ProviderResponse(
                status: AuthorizationStatus::PendingTransfer,
                providerReference: Str::random(10),
                bankDetails: new BankDetailsDTO(
                    accountNumber: '1234567890',
                    bankName: 'Paystack Virtual Bank',
                    accountName: 'Merchant Name',
                    expiresAt: now()->addHours(1)->toIso8601String()
                ),
                rawResponse: [
                    'bank_details' => [
                        'account_number' => '1234567890',
                        'bank_name' => 'Paystack Virtual Bank',
                        'account_name' => 'Merchant Name',
                        'expires_at' => now()->addHours(1)->toIso8601String(),
                    ],
                ]
            );
        }

        if ($dto->reference === 'REF_ACTION') {
            return new ProviderResponse(
                status: AuthorizationStatus::PendingPin,
                providerReference: Str::random(10),
                rawResponse: [
                    'status' => 'requires_pin',
                    'next_step' => 'pin',
                ]
            );
        }

        return new ProviderResponse(
            status: AuthorizationStatus::Success,
            providerReference: Str::random(10),
            rawResponse: ['status' => 'success']
        );
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        // In production: hash_hmac('sha512', json_encode($payload), config('services.paystack.secret')) === $headers['x-paystack-signature']
        return isset($headers['x-paystack-signature']);
    }

    public function normalizeWebhook(array $payload): WebhookPayloadDTO
    {
        $data = $payload['data'] ?? [];

        return new WebhookPayloadDTO(
            providerEventId: (string) ($payload['event'] ?? Str::random(10)),
            eventType: (string) ($payload['event'] ?? 'unknown'),
            reference: (string) ($data['reference'] ?? ''),
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'NGN'),
            status: match ($data['status'] ?? 'unknown') {
                'success' => AuthorizationStatus::Success,
                'failed' => AuthorizationStatus::Failed,
                default => AuthorizationStatus::Pending,
            },
            rawPayload: $payload,
            metadata: $data['metadata'] ?? []
        );
    }

    public function verifyTransaction(string $reference): ProviderResponse
    {
        // Mock logic for verification
        return new ProviderResponse(
            status: AuthorizationStatus::Success, // Assume success for this mock
            providerReference: Str::random(10),
            rawResponse: ['status' => 'success', 'verified' => true]
        );
    }

    public function getFee(PaymentChannel $channel, int $amount): int
    {
        // Mock: 1.5% + NGN 100 is typical, but we return a fixed low-level cost for now or calc dynamically
        // Returning flat 1000 (10.00) for simplicity in this mock
        return 1000;
    }
}

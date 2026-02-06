<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Adapters;

use App\Domains\Payments\Providers\Contracts\ProviderAdapter;
use App\Domains\Payments\Providers\DataTransferObjects\BankDetailsDTO;
use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\DataTransferObjects\PaymentValidateDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Enums\AuthorizationStatus;
use App\Enums\Currency;
use App\Enums\PaymentChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

final class PaystackAdapter implements ProviderAdapter
{
    public function authorize(PaymentAuthorizeDTO $dto): ProviderResponse
    {
        $payload = [
            'email' => $dto->customer->email,
            'amount' => $dto->amount, // Amount in kobo
            'reference' => $dto->reference,
            'metadata' => $dto->metadata,
        ];

        // Handle specific channel requirements
        if ($dto->channel === PaymentChannel::Card) {
            $payload['card'] = $dto->channelDetails;
        }

        $response = Http::withToken(config('services.paystack.secret'))
            ->post('https://api.paystack.co/charge', $payload);

        if ($response->failed()) {
            return new ProviderResponse(
                status: AuthorizationStatus::Failed,
                providerReference: $response->json('data.reference') ?? $dto->reference,
                rawResponse: $response->json() ?? [],
                metadata: ['error' => $response->reason()]
            );
        }

        $data = $response->json('data');
        $status = $data['status'] ?? 'pending';

        $mappedStatus = match ($status) {
            'success' => AuthorizationStatus::Success,
            'failed' => AuthorizationStatus::Failed,
            'send_pin' => AuthorizationStatus::PendingPin,
            'send_otp' => AuthorizationStatus::PendingOtp,
            'send_phone' => AuthorizationStatus::PendingPhone,
            default => AuthorizationStatus::Pending,
        };

        return new ProviderResponse(
            status: $mappedStatus,
            providerReference: $data['reference'] ?? $dto->reference,
            bankDetails: isset($data['bank']) ? new BankDetailsDTO(
                accountNumber: $data['bank']['account_number'] ?? '',
                bankName: $data['bank']['name'] ?? '',
                accountName: $data['bank']['account_name'] ?? '',
                expiresAt: null
            ) : null,
            rawResponse: $data
        );
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        return hash_equals(
            hash_hmac('sha512', json_encode($payload), config('services.paystack.secret')),
            $headers['x-paystack-signature'] ?? ''
        );
    }

    public function normalizeWebhook(array $payload): WebhookPayloadDTO
    {
        $event = $payload['event'] ?? 'unknown';
        $data = $payload['data'] ?? [];

        return new WebhookPayloadDTO(
            providerEventId: (string) ($data['id'] ?? Str::uuid()),
            eventType: $event,
            reference: $data['reference'] ?? throw new RuntimeException('No reference in webhook'),
            amount: (int) ($data['amount'] ?? 0),
            currency: Currency::tryFrom($data['currency'] ?? 'NGN') ?? Currency::NGN,
            status: match ($data['status'] ?? '') {
                'success' => AuthorizationStatus::Success,
                'failed' => AuthorizationStatus::Failed,
                default => AuthorizationStatus::Pending,
            },
            rawPayload: $payload
        );
    }

    public function verifyTransaction(string $reference): ProviderResponse
    {
        $response = Http::withToken(config('services.paystack.secret'))
            ->get("https://api.paystack.co/transaction/verify/{$reference}");

        if ($response->failed()) {
            return new ProviderResponse(
                status: AuthorizationStatus::Failed,
                providerReference: $reference,
                rawResponse: $response->json() ?? [],
                metadata: ['error' => $response->reason()]
            );
        }

        $data = $response->json('data');

        return new ProviderResponse(
            status: $data['status'] === 'success' ? AuthorizationStatus::Success : AuthorizationStatus::Failed,
            providerReference: $data['reference'],
            rawResponse: $data
        );
    }

    public function validate(string $providerReference, PaymentValidateDTO $dto): ProviderResponse
    {
        // 1. Determine endpoint based on DTO properties
        $endpoint = match (true) {
            ! empty($dto->pin) => '/charge/submit_pin',
            ! empty($dto->otp) => '/charge/submit_otp',
            ! empty($dto->phone) => '/charge/submit_phone',
            ! empty($dto->birthday) => '/charge/submit_birthday',
            ! empty($dto->address) => '/charge/submit_address',
            default => throw new RuntimeException('No valid validation data provided')
        };

        // 2. Prepare payload
        $payload = ['reference' => $providerReference];
        if (! empty($dto->pin)) {
            $payload['pin'] = $dto->pin;
        }
        if (! empty($dto->otp)) {
            $payload['otp'] = $dto->otp;
        }
        if (! empty($dto->phone)) {
            $payload['phone'] = $dto->phone;
        }
        if (! empty($dto->birthday)) {
            $payload['birthday'] = $dto->birthday;
        }
        if (! empty($dto->address)) {
            $payload['address'] = $dto->address;
        }

        // 3. Make Request
        $response = Http::withToken(config('services.paystack.secret'))
            ->post("https://api.paystack.co{$endpoint}", $payload);

        if ($response->failed()) {
            return new ProviderResponse(
                status: AuthorizationStatus::Failed,
                providerReference: $providerReference,
                rawResponse: $response->json() ?? [],
                metadata: ['error' => $response->reason()]
            );
        }

        $data = $response->json('data');
        $status = $data['status'] ?? 'pending';

        $mappedStatus = match ($status) {
            'success' => AuthorizationStatus::Success,
            'send_phone' => AuthorizationStatus::Pending,
            default => AuthorizationStatus::Pending,
        };

        return new ProviderResponse(
            status: $mappedStatus,
            providerReference: $providerReference,
            rawResponse: $data
        );
    }

    public function getFee(PaymentChannel $channel, int $amount): int
    {
        return 1000;
    }
}

<?php

declare(strict_types=1);

namespace App\Supports\Providers\Adapters;

use App\Domains\Payouts\DataTransferObjects\BankAccountDetails;
use App\Domains\Payouts\DataTransferObjects\PayoutTransferData;
use App\Enums\AuthorizationStatus;
use App\Enums\Currency;
use App\Enums\PaymentChannel;
use App\Enums\FeeChannel;
use App\Supports\Providers\Contracts\ProviderAdapter;
use App\Supports\Providers\DataTransferObjects\BankDetailsDTO;
use App\Supports\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Supports\Providers\DataTransferObjects\PaymentValidateDTO;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use App\Supports\Providers\DataTransferObjects\WebhookPayloadDTO;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

        if ($dto->channel === PaymentChannel::BankTransfer) {
            $payload['bank_transfer'] = [
                'account_expires_at' => now()->addMinutes(10)->toIso8601String(),
            ];
        }

        $response = Http::withToken(config('services.paystack.secret'))
            ->post('https://api.paystack.co/charge', $payload);

        Log::info('paystack', $response->json());

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
                accountNumber: $data['account_number'] ?? '',
                bankName: $data['bank']['name'] ?? '',
                accountName: $data['account_name'] ?? '',
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
        $status = $data['status'] ?? 'pending';

        $mappedStatus = match ($status) {
            'success' => AuthorizationStatus::Success,
            'failed' => AuthorizationStatus::Failed,
            default => AuthorizationStatus::Pending,
        };

        return new ProviderResponse(
            status: $mappedStatus,
            providerReference: $data['reference'] ?? $reference,
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
            'failed' => AuthorizationStatus::Failed,
            'send_pin' => AuthorizationStatus::PendingPin,
            'send_otp' => AuthorizationStatus::PendingOtp,
            'send_phone' => AuthorizationStatus::PendingPhone,
            default => AuthorizationStatus::Pending,
        };

        return new ProviderResponse(
            status: $mappedStatus,
            providerReference: $providerReference,
            rawResponse: $data
        );
    }

    public function getFee(FeeChannel $channel, int $amount): int
    {
        return 1000;
    }

    public function initiateTransfer(PayoutTransferData $dto): ProviderResponse
    {
        // 1. Create Transfer Recipient
        $recipientResponse = Http::withToken(config('services.paystack.secret'))
            ->post('https://api.paystack.co/transferrecipient', [
                'type' => 'nuban',
                'name' => $dto->account_name,
                'account_number' => $dto->account_number,
                'bank_code' => $dto->bank_code,
                'currency' => $dto->currency,
            ]);

        if ($recipientResponse->failed()) {
            return new ProviderResponse(
                status: AuthorizationStatus::Failed,
                providerReference: $dto->reference, // No provider ref yet
                rawResponse: $recipientResponse->json() ?? [],
                metadata: ['error' => 'Recipient creation failed: '.$recipientResponse->reason()]
            );
        }

        $recipientCode = $recipientResponse->json('data.recipient_code');

        // 2. Initiate Transfer
        $transferResponse = Http::withToken(config('services.paystack.secret'))
            ->post('https://api.paystack.co/transfer', [
                'source' => 'balance',
                'amount' => $dto->amount,
                'recipient' => $recipientCode,
                'reference' => $dto->reference,
                'reason' => 'Payout '.$dto->reference,
            ]);

        if ($transferResponse->failed()) {
            return new ProviderResponse(
                status: AuthorizationStatus::Failed,
                providerReference: $dto->reference,
                rawResponse: $transferResponse->json() ?? [],
                metadata: ['error' => 'Transfer failed: '.$transferResponse->reason()]
            );
        }

        $data = $transferResponse->json('data');
        $status = $data['status'] ?? 'pending';

        $mappedStatus = match ($status) {
            'success' => AuthorizationStatus::Success,
            'failed' => AuthorizationStatus::Failed,
            default => AuthorizationStatus::Pending,
        };

        return new ProviderResponse(
            status: $mappedStatus,
            providerReference: $data['reference'] ?? $dto->reference, // Paystack ref or our ref?
            // Paystack returns a transfer_code and reference.
            // Usually we store transfer_code as providerReference? Or reference?
            // "reference" in response matches request reference if provided.
            // "transfer_code" is unique Paystack ID (TRF_...).
            // I'll use transfer_code if available, or reference.
            // Actually, providerReference usually means the ID on THEIR system.
            // data['transfer_code']
            rawResponse: $data
        );
    }

    public function verifyTransfer(string $reference): ProviderResponse
    {
        return $this->verifyTransaction($reference);
    }

    public function validateAccount(string $accountNumber, string $bankCode): BankAccountDetails
    {
        try {
            $response = Http::withToken(config('services.paystack.secret'))
                ->get("https://api.paystack.co/bank/resolve?account_number=$accountNumber&bank_code=$bankCode")
                ->throw();

            $data =  $response->json('data');

            return new BankAccountDetails(
                $data['account_name'],
                $data['account_number'],
                $bankCode,
            );
        } catch (Exception $exception) {
            Log::error($exception->getMessage());
            throw new Exception('Account validation failed');
        }
    }
}

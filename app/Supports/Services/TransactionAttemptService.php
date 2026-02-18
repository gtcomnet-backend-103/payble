<?php

declare(strict_types=1);

namespace App\Supports\Services;

use App\Enums\AuthorizationStatus;
use App\Enums\Currency;
use App\Enums\FeeChannel;
use App\Models\AuthorizationAttempt;
use App\Models\Provider;
use App\Supports\Providers\Facades\PaymentProvider;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final readonly class TransactionAttemptService
{
    public function __construct(private FeeCalculator $feeCalculator) {}

    public function createAttempt(int $amount, Currency $currency, FeeChannel $channel, Model $model, Provider $provider)
    {
        $platformFee = $this->feeCalculator->calculate($amount, $currency, $channel);
        $providerFee = PaymentProvider::getFee($provider, $channel, $amount);

        return AuthorizationAttempt::create([
            'provider_reference' => Str::uuid()->toString(),
            'intent_id' => $model->getKey(),
            'intent_type' => $model->getMorphClass(),
            'provider_id' => $provider->id,
            'channel' => $channel,
            'status' => AuthorizationStatus::Pending,
            'fee' => $platformFee,
            'provider_fee' => $providerFee,
            'amount' => $amount,
            'currency' => $currency,
        ]);
    }

    public function saveAttemptResponse(AuthorizationAttempt $attempt, AuthorizationStatus $status, array $rawResponse, array $metadata): AuthorizationAttempt
    {
        $attempt->update([
            'status' => $status,
            'raw_response' => $rawResponse,
            'metadata' => $metadata,
        ]);

        return $attempt;
    }

    /**
     * @throws Exception
     */
    public function continueAttempt(Model $model)
    {
        $lastAttempt = AuthorizationAttempt::where('intent_id', $model->getKey())
            ->where('intent_type', $model->getMorphClass())
            ->validating()
            ->latest()
            ->first();

        if (!$lastAttempt) {
            throw new Exception('No pending authorization attempt found.', 400);
        }

        return AuthorizationAttempt::create([
            'provider_reference' => $lastAttempt->provider_reference,
            'intent_id' => $model->getKey(),
            'intent_type' => $model->getMorphClass(),
            'provider_id' => $lastAttempt->provider_id,
            'channel' => $lastAttempt->channel,
            'status' => $lastAttempt->status,
            'fee' => $lastAttempt->fee,
            'provider_fee' => $lastAttempt->provider_fee,
            'amount' => $lastAttempt->amount,
            'currency' => $lastAttempt->currency,
            'metadata' => array_merge($lastAttempt->metadata ?? [], ['validation_step' => true]),
        ]);
    }
}

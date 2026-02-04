<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Ledger\Actions\RecordPaymentLedgerPostings;
use App\Domains\Providers\Facades\PaymentProvider;
use App\Enums\AuthorizationStatus;
use App\Enums\FeeBearer;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use App\Models\Provider;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\Log;

final readonly class ProcessPaymentAttempt
{
    public function __construct(
        private RecordPaymentLedgerPostings $recordLedger
    ) {}

    public function execute(AuthorizationAttempt $attempt): bool
    {
        // 1. Provider Verification (Never Skip)
        try {
            /** @var Provider $provider */
            $provider = $attempt->provider;
            $verificationResponse = PaymentProvider::verifyTransaction($provider, $attempt->provider_reference);

            if (! $verificationResponse->status->is(AuthorizationStatus::Success)) {
                Log::warning("Transaction not yet successful: {$attempt->provider_reference}");

                return false;
            }
        } catch (Exception $e) {
            Log::error('verification failed from provider: '.$e->getMessage());

            return false;
        }

        // 2. Transaction Management
        $transaction = Transaction::where('payment_intent_id', $attempt->payment_intent_id)->first();

        if (! $transaction) {
            $transaction = Transaction::create([
                'business_id' => $attempt->paymentIntent->business_id,
                'payment_intent_id' => $attempt->payment_intent_id,
                'amount' => $attempt->paymentIntent->amount,
                'currency' => $attempt->currency,
                'status' => 'pending',
                'reference' => $attempt->paymentIntent->reference,
                'channel' => $attempt->channel,
                'mode' => $attempt->paymentIntent->mode,
            ]);
        }

        // 3. Fee Calculation
        $halfFee = (int) bcmul((string) $attempt->fee, '0.5');
        [$merchantFee, $customerFee] = match ($attempt->paymentIntent->bearer) {
            FeeBearer::Merchant => [$attempt->fee, 0],
            FeeBearer::Customer => [0, $attempt->fee],
            FeeBearer::Split => [$halfFee, $halfFee],
        };

        // Recalculate provider fee
        $providerFee = PaymentProvider::getFee(
            $provider,
            $attempt->channel,
            $attempt->amount
        );

        // 4. Ledger Posting
        $this->recordLedger->execute(
            $transaction,
            provider: $provider,
            customerFee: (int) $customerFee,
            businessFee: (int) $merchantFee,
            providerFee: (int) $providerFee
        );

        // 5. State Transitions
        $attempt->update(['status' => AuthorizationStatus::Success]);
        $transaction->update(['status' => PaymentStatus::Success]);
        $attempt->paymentIntent?->update(['status' => PaymentStatus::Success]);

        return true;
    }
}

<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Domains\Ledger\Actions\RecordPaymentLedgerPostings;
use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Enums\AuthorizationStatus;
use App\Enums\FeeBearer;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\AuthorizationAttempt;
use App\Models\Provider;
use App\Models\Transaction;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPaymentAttempt
{
    public function __construct(
        private RecordPaymentLedgerPostings $recordLedger
    ) {}

    public function execute(AuthorizationAttempt $attempt): bool
    {
        // 1. Pre-validation & Idempotency Check
        $attempt = AuthorizationAttempt::query()
            ->where('provider_reference', $attempt->provider_reference)
            ->whereIn('status', [
                AuthorizationStatus::Pending,
                AuthorizationStatus::Success,
                AuthorizationStatus::Failed,
                AuthorizationStatus::PendingTransfer,
            ])->first();

        if (! $attempt) {
            return false;
        }

        $provider = $attempt->provider;
        $payment = $attempt->paymentIntent;

        // 2. External Provider Verification
        try {
            $verificationResponse = PaymentProvider::verifyTransaction($provider, $attempt->provider_reference);

            if (! $verificationResponse->status->isFinal()) {
                Log::warning("Transaction not yet successful from provider {$provider->name}: {$attempt->provider_reference}");

                return false;
            }
        } catch (Exception $e) {
            Log::error("Payment verification failed from provider {$provider->name}: ".$e->getMessage());

            return false;
        }

        // 3. Atomic State Management and Ledger Posting
        return DB::transaction(function () use ($attempt, $payment, $provider) {
            // Re-check attempt status inside transaction for absolute safety
            if ($attempt->completed) {
                return true;
            }

            // A: Transaction Management (Ensures uniqueness and avoids race conditions)
            $transaction = Transaction::firstOrCreate([
                'reference' => $payment->reference,
                'channel' => $attempt->channel,
            ], [
                'business_id' => $payment->business_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'status' => TransactionStatus::Pending,
                'mode' => $payment->mode,
            ]);

            // B: Idempotency (If transaction is already successful, we skip ledger and state updates)
            if ($transaction->status->is(TransactionStatus::Success)) {
                return true;
            }

            // C: Integer-Safe Fee Calculation
            [$merchantFee, $customerFee] = match ($payment->bearer) {
                FeeBearer::Merchant => [$attempt->fee, 0],
                FeeBearer::Customer => [0, $attempt->fee],
                FeeBearer::Split => (function () use ($attempt) {
                    $m = (int) ($attempt->fee / 2);

                    return [$m, $attempt->fee - $m];
                })(),
            };
            $providerFee = $attempt->provider_fee;

            // C: Ledger Posting
            $this->recordLedger->execute(
                $transaction,
                provider: $provider,
                customerFee: $customerFee,
                businessFee: $merchantFee,
                providerFee: $providerFee
            );

            // D: State Transitions
            if (! $attempt->status->is(AuthorizationStatus::Success)) {
                $attempt->transitionTo(AuthorizationStatus::Success);
            }
            $transaction->transitionTo(TransactionStatus::Success);
            $payment->transitionTo(PaymentStatus::Success);
            $attempt->markAsComplete();

            return true;
        });
    }
}

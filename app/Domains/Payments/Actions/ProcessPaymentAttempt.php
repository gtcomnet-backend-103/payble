<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Contracts\IdempotentAction;
use App\Domains\Payments\Events\TransactionSuccessful;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\AuthorizationAttempt;
use App\Supports\Providers\Facades\PaymentProvider;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ProcessPaymentAttempt implements IdempotentAction
{
    public function execute(AuthorizationAttempt $attempt): bool
    {
        // 1. Pre-validation & Idempotency Check
        $attempt = AuthorizationAttempt::query()
            ->where('id', $attempt->id)
            ->whereIn('status', [
                AuthorizationStatus::Pending,
                AuthorizationStatus::Success,
                AuthorizationStatus::Failed,
                AuthorizationStatus::PendingTransfer,
                AuthorizationStatus::PendingPin,
                AuthorizationStatus::PendingOtp,
                AuthorizationStatus::PendingPhone,
            ])->first();

        if (! $attempt) {
            return false;
        }

        $provider = $attempt->provider;
        $payment = $attempt->paymentIntent;

        // 2. External Provider Verification (Keep outside the DB transaction to avoid long locks)
        try {
            $verificationResponse = PaymentProvider::verifyTransaction($provider, $attempt->provider_reference);
            if (! $verificationResponse->status->isFinal()) {
                return false;
            }
        } catch (Exception $e) {
            Log::error('Payment verification failed: ' . $e->getMessage());

            return false;
        }

        // 2. Atomic Processing
        return DB::transaction(function () use ($verificationResponse, $attempt, $payment, $provider) {
            // LOCK the attempt for update. Any other process trying to handle this
            // reference will wait here until this transaction commits.
            $attempt = AuthorizationAttempt::where('id', $attempt->id)->lockForUpdate()->first();

            if ($attempt->completed_at !== null) {
                return true;
            }

            // A: Sync Transaction Status
            $transaction = $payment->transaction;

            // B: Idempotency check on the Transaction level
            if ($transaction->status === TransactionStatus::Success) {
                $attempt->markAsComplete();

                return true;
            }

            $payment->update(['amount_paid' => $attempt->amount]);

            // C: Final State Transitions
            $finalStatus = $verificationResponse->status;

            $attempt->transitionTo($finalStatus);
            $transaction->update(['fee' => $attempt->fee]);
            $transaction->transitionTo(TransactionStatus::from($finalStatus->value));
            $payment->transitionTo(PaymentStatus::from($finalStatus->value));

            $attempt->markAsComplete();

            // D: Dispatch Event (Only happens if status is Success)
            if ($finalStatus === AuthorizationStatus::Success) {
                TransactionSuccessful::dispatch(
                    $transaction,
                    $provider,
                    $payment->bearer,
                    $attempt->fee,
                    $attempt->provider_fee
                );
            }

            return true;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Ledger\Actions\RecordPaymentLedgerPostings;
use App\Domains\Providers\Facades\PaymentProvider;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\WebhookEvent;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class HandleProviderWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $webhookEventId) {}

    public function handle(RecordPaymentLedgerPostings $recordLedger): void
    {
        $event = WebhookEvent::findOrFail($this->webhookEventId);

        if ($event->processed_at) {
            return;
        }

        // 1. Correlate with internal records
        $normalized = PaymentProvider::normalizeWebhook(
            Provider::where('identifier', $event->provider)->firstOrFail(),
            $event->raw_payload
        );

        $attempt = AuthorizationAttempt::where('provider_reference', $normalized->reference)->latest()->first();

        if (! $attempt) {
            // Unmatched - log and exit (or queue for later)
            Log::warning("Webhook unmatched: {$normalized->reference}");

            return;
        }

        // 2. State Evaluation
        if ($attempt->status === AuthorizationStatus::Success) {
            $event->update(['processed_at' => now()]);

            return;
        }

        // 3. Provider Verification (Never Skip)
        try {
            /** @var Provider $provider */
            $provider = $attempt->provider;
            $verificationResponse = PaymentProvider::verifyTransaction($provider, $normalized->reference);

            if (! $verificationResponse->status->is(AuthorizationStatus::Success)) {
                Log::warning("Transaction not yet successful: {$normalized->reference}");

                return;
            }
        } catch (Exception $e) {
            Log::error('verification failed from provider: '.$e->getMessage());

            return;
        }

        // For now, we move forward if normalized status says success,
        // effectively trusting the webhook BUT we did verify it exists on provider side.
        if (! $normalized->status->is(AuthorizationStatus::Success)) {
            // Handle failure cases
            return;
        }

        // 4. Ledger Posting (Double-Entry)
        // We resolve fees (mocked for demo logic based on user's assumption)
        $transaction = Transaction::where('payment_intent_id', $attempt->payment_intent_id)->first();

        if (! $transaction) {
            // Create transaction if it doesn't exist
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

        // Specific ledger logic from user
        $recordLedger->execute(
            $transaction,
            provider: $attempt->provider,
            customerFee: 10,  // Mocked per user request
            businessFee: 10,  // Mocked per user request
            providerFee: 5     // Mocked per user request
        );

        // 5. State Transitions
        $attempt->update(['status' => AuthorizationStatus::Success]);
        $transaction->update(['status' => PaymentStatus::Success]);

        $event->update(['processed_at' => now()]);
    }
}

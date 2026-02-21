<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Jobs;

use App\Domains\Payments\Actions\ProcessPaymentAttempt;
use App\Models\AuthorizationAttempt;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\WebhookEvent;
use App\Supports\Providers\Facades\PaymentProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ProcessWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $webhookEventId) {}

    public function handle(): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);

        if ($event->processed_at) {
            return;
        }

        // 1. Correlate with internal records using the Transaction table
        $webhookPayloadDTO = PaymentProvider::normalizeWebhook(
            Provider::where('identifier', $event->provider)->firstOrFail(),
            (array) $event->raw_payload
        );

        $transaction = \App\Models\Transaction::where('provider_reference', $webhookPayloadDTO->reference)->first();

        if (is_null($transaction)) {
            Log::warning("Webhook unmatched {$event->provider}: {$webhookPayloadDTO->reference}");
            $event->update([
                'processed_at' => now(),
                'feedback' => "No transaction with provider reference [$webhookPayloadDTO->reference] found",
            ]);

            return;
        }

        // 2. Delegate Processing based on source type
        $processed = false;

        if ($transaction->source_type === (new PaymentIntent())->getMorphClass()) {
            if ($transaction->status === \App\Enums\TransactionStatus::Success) {
                $event->update([
                    'processed_at' => now(),
                    'feedback' => 'payment already processed',
                ]);

                return;
            }

            /** @var ?AuthorizationAttempt $attempt */
            $attempt = AuthorizationAttempt::where('provider_reference', $webhookPayloadDTO->reference)
                ->pending()
                ->latest()
                ->first();

            if ($attempt && $event->event_type === 'charge.success') {
                $processed = app(ProcessPaymentAttempt::class)->execute($attempt);
            } else {
                $feedback = $attempt ? "Unsupported event [{$event->event_type}] for pending attempt" : "No pending payment attempt found for reference [{$webhookPayloadDTO->reference}]";
                $event->update([
                    'processed_at' => now(),
                    'feedback' => $feedback,
                ]);

                return;
            }
        } elseif ($transaction->source_type === (new \App\Models\Payout())->getMorphClass()) {
            /** @var \App\Models\Payout $payout */
            $payout = $transaction->source;
            if ($payout) {
                app(\App\Domains\Payouts\Actions\ProcessPayout::class)->execute($payout);
                $processed = true;
            }
        }

        if ($processed) {
            $event->update([
                'processed_at' => now(),
                'feedback' => 'event processed successfully',
            ]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Payments\Actions\ProcessPaymentAttempt;
use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class ProcessWebhook implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $webhookEventId) {}

    /**
     * @param ProcessPaymentAttempt $action
     */
    public function handle(ProcessPaymentAttempt $action): void
    {
        $event = WebhookEvent::query()->findOrFail($this->webhookEventId);

        if ($event->processed_at) {
            return;
        }

        // 1. Correlate with internal records
        $webhookPayloadDTO = PaymentProvider::normalizeWebhook(
            Provider::where('identifier', $event->provider)->firstOrFail(),
            (array) $event->raw_payload
        );

        /** @var ?AuthorizationAttempt $attempt */
        $attempt = AuthorizationAttempt::where('provider_reference', $webhookPayloadDTO->reference)
            ->pending()
            ->latest()
            ->first();

        if (is_null($attempt)) {
            Log::warning("Webhook unmatched: {$webhookPayloadDTO->reference}");
            $event->update([
                'processed_at' => now(),
                'feedback' => "No payment attempt with reference [$webhookPayloadDTO->reference] found",
            ]);

            return;
        }

        // 2. State Evaluation
        /** @var ?PaymentIntent $payment */
        $payment = $attempt->paymentIntent;
        if (PaymentStatus::Success->is($payment->status ?? PaymentStatus::Pending)) {
            $event->update([
                'processed_at' => now(),
                'feedback' => 'payment already processed',
            ]);

            return;
        }

        // 3. Delegate Processing
        $processed = $action->execute($attempt);

        if ($processed) {
            $event->update([
                'processed_at' => now(),
                'feedback' => 'payment processed',
            ]);
        }
    }
}

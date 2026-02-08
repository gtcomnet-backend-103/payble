<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Transaction;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SendBusinessWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    public $backoff = [10, 60, 180];

    public function __construct(
        public Transaction $transaction
    ) {}

    public function handle(): void
    {
        $business = $this->transaction->business;
        $url = $business->webhook_url;

        if (! $url) {
            return;
        }

        $payload = [
            'event' => 'transaction.successful',
            'data' => [
                'reference' => $this->transaction->reference,
                'amount' => $this->transaction->amount,
                'currency' => $this->transaction->currency->value,
                'status' => $this->transaction->status->value,
                'channel' => $this->transaction->channel?->value,
                'customer_email' => $this->transaction->paymentIntent->customer?->email,
                'paid_at' => $this->transaction->updated_at->toIso8601String(),
                'metadata' => $this->transaction->metadata,
            ],
        ];

        try {
            $response = Http::timeout(10)
                ->post($url, $payload);

            if ($response->failed()) {
                Log::warning("Webhook failed for Business {$business->id} Transaction {$this->transaction->id}: {$response->status()}");
                $this->release(10);
            }
        } catch (Exception $e) {
            Log::error("Webhook exception for Business {$business->id}: {$e->getMessage()}");
            $this->release(10);
        }
    }
}

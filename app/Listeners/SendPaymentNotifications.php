<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Payments\Events\TransactionSuccessful;
use App\Jobs\SendBusinessWebhook;
use App\Notifications\PaymentReceipt;
use App\Notifications\PaymentReceived;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendPaymentNotifications implements ShouldQueue
{
    public function handle(TransactionSuccessful $event): void
    {
        $transaction = $event->transaction;
        $business = $transaction->business;
        $paymentIntent = $transaction->source;
        $customer = $paymentIntent->customer;

        // 1. Notify Business (Email)
        $business->notify(new PaymentReceived($transaction));

        // 2. Notify Customer (Email)
        if ($customer && $customer->email) {
            $customer->notify(new PaymentReceipt($transaction));
        }

        // 3. Send Webhook to Business
        if ($business->webhook_url && $business->isVerified()) {
            SendBusinessWebhook::dispatch($transaction);
        }
    }
}

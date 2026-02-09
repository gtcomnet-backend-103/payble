<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Transaction $transaction
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = number_format($this->transaction->amount / 100, 2);
        $currency = $this->transaction->currency->value;
        $reference = $this->transaction->reference;
        $businessName = $this->transaction->business->name;
        $date = $this->transaction->created_at->format('M d, Y H:i');

        return (new MailMessage)
            ->subject("Receipt from {$businessName}: {$currency} {$amount}")
            ->line("Thank you for your payment to {$businessName}.")
            ->line('Here are your payment details:')
            ->line("**Amount Paid:** {$currency} {$amount}")
            ->line("**Date:** {$date}")
            ->line("**Reference:** {$reference}")
            ->line('Your transaction was successful.')
            ->line('If you have any questions, contact the business directly.');
    }
}

<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentReceived extends Notification implements ShouldQueue
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
        $customerEmail = $this->transaction->paymentIntent->customer?->email ?? 'N/A';
        $reference = $this->transaction->reference;

        return (new MailMessage)
            ->subject("Payment Received: {$currency} {$amount} from Customer")
            ->greeting("Hello {$notifiable->name},")
            ->line("You received a new payment of **{$currency} {$amount}**.")
            ->line('Here are the details:')
            ->line("**Amount:** {$currency} {$amount}")
            ->line("**Reference:** {$reference}")
            ->line("**Customer:** {$customerEmail}")
            ->action('View Transaction', route('filament.dashboard.resources.transactions.index', [
                'tenant' => $this->transaction->business,
                'tableSearch' => $reference,
            ]))
            ->line('Thank you for using our service!');
    }
}

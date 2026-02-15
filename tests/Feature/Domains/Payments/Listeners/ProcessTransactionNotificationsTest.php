<?php

declare(strict_types=1);

use App\Domains\Payments\Events\TransactionSuccessful;
use App\Jobs\SendBusinessWebhook;
use App\Listeners\SendPaymentNotifications;
use App\Models\Business;
use App\Models\Customer;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\User;
use App\Notifications\PaymentReceipt;
use App\Notifications\PaymentReceived;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('sends notifications and webhook on successful transaction', function () {
    Notification::fake();
    Bus::fake();

    $user = User::factory()->create();
    $business = Business::create([
        'name' => 'Web Business',
        'email' => 'web@business.com',
        'owner_id' => $user->id,
        'webhook_url' => 'https://example.com/webhook',
        'verified_at' => now(),
    ]);

    $customer = Customer::create([
        'business_id' => $business->id,
        'email' => 'customer@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
    ]);

    $provider = Provider::factory()->create([
        'name' => 'Paystack',
        'identifier' => 'paystack',
        'supported_channels' => ['card'],
        'is_active' => true,
    ]);

    $intent = PaymentIntent::create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'amount' => 1000, // 10.00
        'amount_paid' => 1000,
        'currency' => 'NGN',
        'status' => App\Enums\PaymentStatus::Success,
        'bearer' => App\Enums\FeeBearer::ACCOUNT,
        'mode' => App\Enums\PaymentMode::Live,
        'reference' => 'REF_SHARED_123',
    ]);

    $transaction = $intent->transaction()->create([
        'business_id' => $business->id,
        'currency' => 'NGN',
        'amount' => 1000,
        'status' => App\Enums\TransactionStatus::Success,
        'mode' => App\Enums\PaymentMode::Live,
        'reference' => 'REF_SHARED_123',
    ]);

    $event = new TransactionSuccessful(
        transaction: $transaction,
        provider: $provider,
        bearer: App\Enums\FeeBearer::ACCOUNT,
        totalFee: 0,
        providerFee: 0
    );

    $listener = new SendPaymentNotifications();
    $listener->handle($event);

    // Assert Business Notification
    Notification::assertSentTo(
        [$business],
        PaymentReceived::class,
        function ($notification, $channels) use ($business) {
            $mail = $notification->toMail($business);
            $content = (string) $mail->render();

            $subjectCheck = $mail->subject === 'Payment Received: NGN 10.00 from Customer';
            $bodyCheck = str_contains($content, 'You received a new payment');

            return $subjectCheck && $bodyCheck;
        }
    );

    // Assert Customer Notification
    Notification::assertSentTo(
        [$customer],
        PaymentReceipt::class,
        function ($notification, $channels) use ($customer) {
            $mail = $notification->toMail($customer);
            $content = (string) $mail->render();

            $subjectCheck = str_contains($mail->subject, 'Receipt from Web Business');
            $bodyCheck = str_contains($content, 'Thank you for your payment');

            return $subjectCheck && $bodyCheck;
        }
    );

    // Assert Webhook Job
    Bus::assertDispatched(SendBusinessWebhook::class, function ($job) use ($transaction) {
        return $job->transaction->id === $transaction->id;
    });
});

it('does not send webhook if business has no webhook url', function () {
    Notification::fake();
    Bus::fake();

    $user = User::factory()->create();
    $business = Business::create([
        'name' => 'Web Business',
        'email' => 'web@business.com',
        'owner_id' => $user->id,
        'webhook_url' => null, // No URL
        'verified_at' => now(),
    ]);

    $customer = Customer::create([
        'business_id' => $business->id,
        'email' => 'customer@example.com',
    ]);

    $provider = Provider::factory()->create([
        'name' => 'Paystack',
        'identifier' => 'paystack',
        'supported_channels' => ['card'],
        'is_active' => true,
    ]);

    $intent = PaymentIntent::create([
        'business_id' => $business->id,
        'customer_id' => $customer->id,
        'amount' => 1000,
        'amount_paid' => 1000,
        'currency' => 'NGN',
        'status' => App\Enums\PaymentStatus::Success,
        'bearer' => App\Enums\FeeBearer::ACCOUNT,
        'mode' => App\Enums\PaymentMode::Live,
        'reference' => 'REF_SHARED_NO_WEBHOOK',
    ]);

    $transaction = $intent->transaction()->create([
        'business_id' => $business->id,
        'currency' => 'NGN',
        'amount' => 1000,
        'status' => App\Enums\TransactionStatus::Success,
        'mode' => App\Enums\PaymentMode::Live,
        'reference' => 'REF_SHARED_NO_WEBHOOK',
    ]);

    $event = new TransactionSuccessful(
        transaction: $transaction,
        provider: $provider,
        bearer: App\Enums\FeeBearer::ACCOUNT,
        totalFee: 0,
        providerFee: 0
    );

    $listener = new SendPaymentNotifications();
    $listener->handle($event);

    Bus::assertNotDispatched(SendBusinessWebhook::class);

    // Notifications should still go out
    Notification::assertSentTo([$business], PaymentReceived::class);
});

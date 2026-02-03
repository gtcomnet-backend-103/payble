<?php

declare(strict_types=1);

use App\Domains\Payments\Actions\ResolvePaymentFee;
use App\Enums\PaymentChannel;
use App\Models\Business;
use App\Models\FeeConfig;
use App\Models\PaymentIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->paymentIntent = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 10000, // 10,000 NGN
    ]);
});

it('resolves global fee when no business-specific fee exists', function () {
    FeeConfig::create([
        'channel' => PaymentChannel::Card,
        'percentage' => 1.5,
        'fixed_amount' => 100,
        'min_fee' => 0,
        'is_active' => true,
    ]);

    $resolvePaymentFee = new ResolvePaymentFee();
    $fee = $resolvePaymentFee->execute($this->paymentIntent, PaymentChannel::Card);

    // (10000 * 1.5 / 100) + 100 = 150 + 100 = 250
    expect($fee)->toBe(250);
});

it('resolves business-specific fee over global fee', function () {
    // Global
    FeeConfig::create([
        'channel' => PaymentChannel::Card,
        'percentage' => 1.5,
        'fixed_amount' => 100,
        'is_active' => true,
    ]);

    // Business specific
    FeeConfig::create([
        'business_id' => $this->business->id,
        'channel' => PaymentChannel::Card,
        'percentage' => 1.0,
        'fixed_amount' => 50,
        'is_active' => true,
    ]);

    $resolvePaymentFee = new ResolvePaymentFee();
    $fee = $resolvePaymentFee->execute($this->paymentIntent, PaymentChannel::Card);

    // (10000 * 1.0 / 100) + 50 = 100 + 50 = 150
    expect($fee)->toBe(150);
});

it('respects min and max fee constraints', function () {
    FeeConfig::create([
        'channel' => PaymentChannel::Card,
        'percentage' => 1.0,
        'fixed_amount' => 0,
        'min_fee' => 200,
        'max_fee' => 500,
        'is_active' => true,
    ]);

    $resolvePaymentFee = new ResolvePaymentFee();

    // Case 1: Below min
    $payment1 = PaymentIntent::factory()->create(['amount' => 10000]); // Fee = 100
    expect($resolvePaymentFee->execute($payment1, PaymentChannel::Card))->toBe(200);

    // Case 2: Above max
    $payment2 = PaymentIntent::factory()->create(['amount' => 100000]); // Fee = 1000
    expect($resolvePaymentFee->execute($payment2, PaymentChannel::Card))->toBe(500);
});

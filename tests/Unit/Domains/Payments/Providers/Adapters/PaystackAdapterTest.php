<?php

declare(strict_types=1);

use App\Domains\Payments\Providers\Adapters\PaystackAdapter;
use App\Domains\Payments\Providers\DataTransferObjects\CustomerDTO;
use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Enums\AuthorizationStatus;
use App\Enums\Currency;
use App\Enums\PaymentChannel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

it('calls charge endpoint and returns success response', function () {
    // Arrange
    Config::set('services.paystack.secret', 'sk_test_123');

    Http::fake([
        'https://api.paystack.co/charge' => Http::response([
            'status' => true,
            'message' => 'Charge successful',
            'data' => [
                'reference' => 'ref_123',
                'status' => 'success',
                'amount' => 10000,
            ],
        ], 200),
    ]);

    $adapter = new PaystackAdapter();
    $dto = new PaymentAuthorizeDTO(
        reference: 'ref_123',
        amount: 10000,
        currency: Currency::NGN,
        channel: PaymentChannel::Card,
        customer: new CustomerDTO('John', 'Doe', 'john@example.com', null),
        metadata: ['authorization_code' => 'auth_code_123']
    );

    // Act
    $response = $adapter->authorize($dto);

    // Assert
    expect($response->status->is(AuthorizationStatus::Success))->toBeTrue()
        ->and($response->providerReference)->toBe('ref_123');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.paystack.co/charge'
            && $request['amount'] === 10000
            && $request['email'] === 'john@example.com'
            && $request['authorization_code'] === 'auth_code_123';
    });
});

it('handles pending pin response', function () {
    // Arrange
    Config::set('services.paystack.secret', 'sk_test_123');

    Http::fake([
        'https://api.paystack.co/charge' => Http::response([
            'status' => true,
            'message' => 'PIN required',
            'data' => [
                'reference' => 'ref_pin',
                'status' => 'send_pin',
            ],
        ], 200),
    ]);

    $adapter = new PaystackAdapter();
    $dto = new PaymentAuthorizeDTO(
        reference: 'ref_pin',
        amount: 5000,
        currency: Currency::NGN,
        channel: PaymentChannel::Card,
        customer: new CustomerDTO('Jane', 'Doe', 'jane@example.com', null)
    );

    // Act
    $response = $adapter->authorize($dto);

    // Assert
    expect($response->status->is(AuthorizationStatus::PendingPin))->toBeTrue();
});

it('handles failed request', function () {
    // Arrange
    Config::set('services.paystack.secret', 'sk_test_123');

    Http::fake([
        'https://api.paystack.co/charge' => Http::response([
            'status' => false,
            'message' => 'Charge failed',
        ], 400),
    ]);

    $adapter = new PaystackAdapter();
    $dto = new PaymentAuthorizeDTO(
        reference: 'ref_fail',
        amount: 5000,
        currency: Currency::NGN,
        channel: PaymentChannel::Card,
        customer: new CustomerDTO('Fail', 'User', 'fail@example.com', null)
    );

    // Act
    $response = $adapter->authorize($dto);

    // Assert
    expect($response->status->is(AuthorizationStatus::Failed))->toBeTrue();
});

it('validates using PaymentValidateDTO', function () {
    // Arrange
    Config::set('services.paystack.secret', 'sk_test_123');

    Http::fake([
        'https://api.paystack.co/charge/submit_otp' => Http::response([
            'status' => true,
            'message' => 'Charge attempted',
            'data' => [
                'reference' => 'ref_otp_valid',
                'status' => 'success',
            ],
        ], 200),
    ]);

    $adapter = new PaystackAdapter();
    $dto = new App\Domains\Payments\Providers\DataTransferObjects\PaymentValidateDTO(
        otp: '123456'
    );

    // Act
    $response = $adapter->validate('ref_otp_valid', $dto);

    // Assert
    expect($response->status->is(AuthorizationStatus::Success))->toBeTrue();

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.paystack.co/charge/submit_otp'
            && $request['otp'] === '123456'
            && $request['reference'] === 'ref_otp_valid';
    });
});

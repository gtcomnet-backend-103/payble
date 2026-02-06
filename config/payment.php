<?php

declare(strict_types=1);

use App\Enums\PaymentChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Providers
    |--------------------------------------------------------------------------
    |
    | This file defines the supported payment providers and their default
    | configurations. These values are used to synchronize the database
    | state via the `payment:providers-sync` command.
    |
    */

    'providers' => [
        [
            'name' => 'Paystack',
            'identifier' => 'paystack',
            'is_active' => true,
            'is_healthy' => true,
            'supported_channels' => [
                PaymentChannel::Card->value,
                PaymentChannel::BankTransfer->value,
            ],
            'metadata' => [
                'fee_percentage' => 1.5,
                'fixed_fee' => 10000, // Amount in minor units (100.00)
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Adapters
    |--------------------------------------------------------------------------
    |
    | This maps provider identifiers to their respective adapter classes.
    |
    */

    'adapters' => [
        'paystack' => App\Domains\Payments\Providers\Adapters\PaystackAdapter::class,
    ],

];

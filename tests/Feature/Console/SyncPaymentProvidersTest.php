<?php

use App\Enums\PaymentChannel;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('synchronizes payment providers from configuration', function () {
    // Assert database is empty
    expect(Provider::count())->toBe(0);

    // Mock config to ensure predictable test environment
    config(['payment.providers' => [
        [
            'name' => 'Test Provider',
            'identifier' => 'test_id',
            'is_active' => true,
            'is_healthy' => true,
            'supported_channels' => [PaymentChannel::Card->value],
            'metadata' => ['key' => 'value'],
        ],
    ]]);

    // Run the command
    $this->artisan('payment:providers-sync')
        ->expectsOutput('Starting provider synchronization...')
        ->expectsOutput('Created provider: Test Provider (test_id)')
        ->expectsOutput('Provider synchronization completed successfully.')
        ->assertExitCode(0);

    // Verify database
    expect(Provider::count())->toBe(1);

    $provider = Provider::where('identifier', 'test_id')->first();
    expect($provider->name)->toBe('Test Provider');
    expect($provider->supported_channels)->toBe([PaymentChannel::Card->value]);
});

it('updates existing providers instead of creating duplicates', function () {
    // Manually create a provider
    Provider::create([
        'name' => 'Old Name',
        'identifier' => 'test_id',
        'is_active' => false,
        'is_healthy' => false,
        'supported_channels' => [],
    ]);

    config(['payment.providers' => [
        [
            'name' => 'New Name',
            'identifier' => 'test_id',
            'is_active' => true,
            'is_healthy' => true,
            'supported_channels' => [PaymentChannel::Card->value],
        ],
    ]]);

    // Run the command
    $this->artisan('payment:providers-sync')
        ->expectsOutput('Updated provider: New Name (test_id)')
        ->assertExitCode(0);

    // Verify database
    expect(Provider::count())->toBe(1);

    $provider = Provider::where('identifier', 'test_id')->first();
    expect($provider->name)->toBe('New Name');
    expect($provider->is_active)->toBeTrue();
});

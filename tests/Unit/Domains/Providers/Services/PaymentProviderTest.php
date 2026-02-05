<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Providers\Services;

use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Domains\Payments\Providers\Services\PaymentProvider as PaymentProviderService;
use App\Domains\Payments\Providers\Services\ProviderResolver;
use App\Enums\PaymentChannel;
use App\Models\Provider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class PaymentProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_fee_delegates_to_resolver(): void
    {
        // Arrange
        $provider = Provider::factory()->make(['identifier' => 'mock_provider']);
        $channel = PaymentChannel::BankTransfer;
        $amount = 5000;
        $expectedFee = 1500;

        $resolver = Mockery::mock(ProviderResolver::class);
        $adapter = Mockery::mock(\App\Domains\Payments\Providers\Contracts\ProviderAdapter::class);

        $resolver->shouldReceive('resolve')
            ->once()
            ->with($provider)
            ->andReturn($adapter);

        $adapter->shouldReceive('getFee')
            ->once()
            ->with($channel, $amount)
            ->andReturn($expectedFee);

        $service = new PaymentProviderService($resolver);

        // Act
        $fee = $service->getFee($provider, $channel, $amount);

        // Assert
        $this->assertEquals($expectedFee, $fee);
    }

    public function test_facade_get_fee(): void
    {
        // Arrange
        $provider = Provider::factory()->create(['identifier' => 'paystack']);

        // Act
        // Paystack adapter returns fixed 1000 in our mock implementation
        $fee = PaymentProvider::getFee($provider, PaymentChannel::Card, 5000);

        // Assert
        $this->assertEquals(1000, $fee);
    }
}

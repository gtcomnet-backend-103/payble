<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentChannel;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Provider>
 */
final class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company().' Payments',
            'identifier' => $this->faker->unique()->slug(2),
            'is_active' => true,
            'is_healthy' => true,
            'supported_channels' => [
                PaymentChannel::Card->value,
                PaymentChannel::BankTransfer->value,
            ],
            'metadata' => [
                'fee_percentage' => 0.1,
                'max_daily_limit' => 1000000,
            ],
        ];
    }

    public function test(): self
    {
        return $this->state(function () {
            return [
                'name' => 'Test Provider',
                'identifier' => 'test_provider',
                'is_active' => true,
                'is_healthy' => true,
                'supported_channels' => ['card'],
            ];
        });
    }
}

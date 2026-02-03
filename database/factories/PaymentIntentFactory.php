<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
use App\Enums\FeeBearer;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\PaymentIntent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentIntent>
 */
final class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'amount' => $this->faker->numberBetween(1000, 1000000),
            'currency' => Currency::NGN,
            'reference' => 'TRX_' . Str::random(10),
            'status' => PaymentStatus::Initiated,
            'bearer' => FeeBearer::Merchant,
            'mode' => PaymentMode::Test,
            'metadata' => [],
        ];
    }
}

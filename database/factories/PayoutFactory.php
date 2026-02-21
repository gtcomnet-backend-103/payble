<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Currency;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payout>
 */

use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Models\Admin;
use App\Models\Business;
use App\Models\Payout;
use Illuminate\Database\Eloquent\Factories\Factory;

final class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'originator_type' => Admin::class,
            'originator_id' => Admin::factory(),
            'amount' => $this->faker->numberBetween(100000, 1000000),
            'fee' => 5000,
            'currency' => Currency::NGN,
            'mode' => PaymentMode::Test,
            'status' => PayoutStatus::Pending,
            'reference' => 'PAY_'.\Illuminate\Support\Str::random(12),
            'requires_otp' => false,
            'metadata' => [],
        ];
    }
}

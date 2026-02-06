<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\PaymentIntent;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'payment_intent_id' => PaymentIntent::factory(),
            'amount' => $this->faker->numberBetween(100, 1000000),
            'currency' => 'NGN',
            'status' => 'success',
            'reference' => 'TXN_' . $this->faker->unique()->bothify('??###'),
            'mode' => 'test',
            'channel' => 'card',
        ];
    }
}

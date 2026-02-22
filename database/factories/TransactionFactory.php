<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
final class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->numberBetween(100, 1000000);

        return [
            'business_id' => Business::factory(),
            'source_type' => Business::class,
            'source_id' => Business::factory(),
            'currency' => 'NGN',
            'amount' => $amount,
            'fee' => 100,
            'status' => 'success',
            'reference' => 'TXN_' . $this->faker->unique()->bothify('??###'),
            'mode' => 'test',
        ];
    }
}

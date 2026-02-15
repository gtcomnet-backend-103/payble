<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankAccount>
 */
final class BankAccountFactory extends Factory
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
            'currency' => 'NGN',
            'bank_code' => $this->faker->numerify('###'),
            'account_number' => $this->faker->numerify('##########'),
            'account_name' => $this->faker->name(),
            'verified_at' => now(),
        ];
    }
}

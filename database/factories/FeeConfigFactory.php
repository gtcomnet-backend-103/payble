<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\FeeConfig;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

final class FeeConfigFactory extends Factory
{
    protected $model = FeeConfig::class;

    public function definition(): array
    {
        return [
            'min_fee' => 0,
            'max_fee' => 0,
            'percentage' => 0,
            'fixed_amount' => 0,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

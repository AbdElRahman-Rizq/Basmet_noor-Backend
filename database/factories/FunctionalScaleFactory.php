<?php

namespace Database\Factories;

use App\Models\FunctionalScale;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FunctionalScale>
 */
class FunctionalScaleFactory extends Factory
{
    protected $model = FunctionalScale::class;

    public function definition(): array
    {
        return [
            'name' => 'Scale '.Str::upper(fake()->unique()->bothify('####-????')),
            'description' => null,
            'display_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\ReinforcerCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReinforcerCatalog>
 */
class ReinforcerCatalogFactory extends Factory
{
    protected $model = ReinforcerCatalog::class;

    public function definition(): array
    {
        return [
            'category' => fake()->randomElement(['material', 'social', 'activity', 'food']),
            'name' => 'Reinforcer '.fake()->unique()->numberBetween(1, 100000),
            'is_active' => true,
            'display_order' => fake()->numberBetween(1, 100),
        ];
    }
}

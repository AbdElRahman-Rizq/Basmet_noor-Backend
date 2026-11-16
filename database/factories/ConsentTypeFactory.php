<?php

namespace Database\Factories;

use App\Models\ConsentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentType>
 */
class ConsentTypeFactory extends Factory
{
    protected $model = ConsentType::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'description' => 'Placeholder description — no legal wording is invented in tests.',
            'is_required' => true,
            'display_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
        ];
    }

    public function optional(): static
    {
        return $this->state(fn () => ['is_required' => false]);
    }
}

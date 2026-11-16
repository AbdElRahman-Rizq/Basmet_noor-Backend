<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Specialist;
use App\Models\TreatmentPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        return [
            'plan_id' => TreatmentPlan::factory(),
            'specialist_id' => Specialist::factory(),
            'title' => fake()->sentence(4),
            'description' => null,
            'display_order' => fake()->numberBetween(1, 20),
            'progress_percentage' => 0,
        ];
    }
}

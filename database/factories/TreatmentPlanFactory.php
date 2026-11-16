<?php

namespace Database\Factories;

use App\Models\CaseFile;
use App\Models\TreatmentPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TreatmentPlan>
 */
class TreatmentPlanFactory extends Factory
{
    protected $model = TreatmentPlan::class;

    public function definition(): array
    {
        $start = now()->subDays(fake()->numberBetween(1, 30))->toDateString();

        return [
            'case_id' => CaseFile::factory(),
            'title' => fake()->sentence(4),
            'plan_type' => null,
            'start_date' => $start,
            'end_date' => null,
            'status' => null,
            'created_by_user_id' => User::factory(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\InitialAssessment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InitialAssessment>
 */
class InitialAssessmentFactory extends Factory
{
    protected $model = InitialAssessment::class;

    public function definition(): array
    {
        return [
            'child_id' => Child::factory(),
            'assessment_date' => now()->toDateString(),
            'notes' => null,
        ];
    }
}

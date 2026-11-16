<?php

namespace Database\Factories;

use App\Models\Child;
use App\Models\KnowledgeSource;
use App\Models\ParentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Child>
 */
class ChildFactory extends Factory
{
    protected $model = Child::class;

    public function definition(): array
    {
        $sequence = (int) DB::scalar("select nextval('child_code_sequence')");

        return [
            'parent_id' => ParentProfile::factory(),
            'child_code' => 'ABCD'.$sequence,
            'sequence_number' => $sequence,
            'age' => fake()->numberBetween(1, 17),
            'age_recorded_at' => now()->toDateString(),
            'nationality' => fake()->country(),
            'neighborhood' => fake()->city(),
            'initial_problem' => fake()->sentence(),
            'knowledge_source_id' => KnowledgeSource::factory(),
            'identity_verified' => false,
        ];
    }

    public function withoutKnowledgeSource(): static
    {
        return $this->state(fn () => ['knowledge_source_id' => null]);
    }
}

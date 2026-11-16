<?php

namespace Database\Factories;

use App\Models\KnowledgeSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeSource>
 */
class KnowledgeSourceFactory extends Factory
{
    protected $model = KnowledgeSource::class;

    public function definition(): array
    {
        return [
            'name' => 'Source '.fake()->unique()->sentence(2),
            'is_active' => true,
        ];
    }
}

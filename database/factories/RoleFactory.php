<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => Str::upper(fake()->unique()->bothify('ROLE_####_????')),
            'description' => null,
            'is_system_role' => false,
            'level' => fake()->numberBetween(0, 100),
        ];
    }
}

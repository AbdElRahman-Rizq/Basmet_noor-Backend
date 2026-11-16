<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\CaseFile;
use App\Models\Child;
use App\Models\Specialist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseFile>
 */
class CaseFileFactory extends Factory
{
    protected $model = CaseFile::class;

    public function definition(): array
    {
        return [
            'child_id' => Child::factory(),
            'branch_id' => Branch::factory(),
            'specialist_id' => null,
            'assigned_at' => null,
            'assigned_by_user_id' => null,
            'status' => null,
        ];
    }

    public function assignedTo(Specialist $specialist, User $assignedBy): static
    {
        return $this->state(fn () => [
            'specialist_id' => $specialist->id,
            'assigned_at' => now(),
            'assigned_by_user_id' => $assignedBy->id,
        ]);
    }
}

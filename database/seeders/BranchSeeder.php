<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Two placeholder branches (the design requires two, names are not specified).
     * Administrators replace these with real branch names in Gate D.
     */
    public const BRANCHES = [
        'Main Branch (Placeholder)',
        'Second Branch (Placeholder)',
    ];

    public function run(): void
    {
        foreach (self::BRANCHES as $name) {
            Branch::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}

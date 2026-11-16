<?php

namespace Database\Seeders;

use App\Models\KnowledgeSource;
use Illuminate\Database\Seeder;

class KnowledgeSourceSeeder extends Seeder
{
    /**
     * Placeholder knowledge sources — the approved documentation lists the
     * lookup but not its final values.
     */
    public const SOURCES = [
        'Social Media (Placeholder)',
        'Word of Mouth (Placeholder)',
        'Referral (Placeholder)',
        'Other (Placeholder)',
    ];

    public function run(): void
    {
        foreach (self::SOURCES as $name) {
            KnowledgeSource::firstOrCreate(['name' => $name], ['is_active' => true]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\ConsentType;
use Illuminate\Database\Seeder;

class ConsentTypeSeeder extends Seeder
{
    /**
     * Exactly three required consent types (design §4.4).
     *
     * The final names and legal wording are NOT approved yet. These are
     * explicit placeholders: no legal text is invented and no real consent
     * may be recorded until the center provides approved wording (D-050).
     */
    public const TYPES = [
        ['name' => 'Consent Type 01 (Placeholder)', 'display_order' => 1],
        ['name' => 'Consent Type 02 (Placeholder)', 'display_order' => 2],
        ['name' => 'Consent Type 03 (Placeholder)', 'display_order' => 3],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $type) {
            ConsentType::updateOrCreate(
                ['name' => $type['name']],
                [
                    'description' => 'Placeholder description — center-approved consent wording is required before recording real consents.',
                    'is_required' => true,
                    'display_order' => $type['display_order'],
                    'is_active' => true,
                ],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\FunctionalScale;
use Illuminate\Database\Seeder;

class FunctionalScaleSeeder extends Seeder
{
    /**
     * Ten placeholder scales (P-09 scale names are still pending).
     * Scores are integers 0..10 (D-047).
     */
    public function run(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $order = str_pad((string) $i, 2, '0', STR_PAD_LEFT);

            FunctionalScale::updateOrCreate(
                ['name' => "Functional Scale {$order} (Placeholder)"],
                [
                    'description' => 'Placeholder — scale name/description pending client approval (P-09).',
                    'display_order' => $i,
                    'is_active' => true,
                ],
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\ReinforcerCatalog;
use Illuminate\Database\Seeder;

class ReinforcerCatalogSeeder extends Seeder
{
    /**
     * Placeholder items for the four approved categories.
     * Final catalogue content is not part of the approved schema.
     */
    public const CATEGORIES = ['material', 'social', 'activity', 'food'];

    public function run(): void
    {
        $order = 1;

        foreach (self::CATEGORIES as $category) {
            for ($i = 1; $i <= 2; $i++) {
                ReinforcerCatalog::updateOrCreate(
                    [
                        'category' => $category,
                        'name' => ucfirst($category)." Reward {$i} (Placeholder)",
                    ],
                    [
                        'is_active' => true,
                        'display_order' => $order++,
                    ],
                );
            }
        }
    }
}

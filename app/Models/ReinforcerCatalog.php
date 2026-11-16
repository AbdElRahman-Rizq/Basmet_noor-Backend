<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReinforcerCatalog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'reinforcer_catalog';

    protected $fillable = [
        'category',
        'name',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function assessmentReinforcers(): HasMany
    {
        return $this->hasMany(AssessmentReinforcer::class, 'reinforcer_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentReinforcer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'assessment_id',
        'reinforcer_id',
        'reinforcer_name_snapshot',
        'category_snapshot',
        'notes',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(InitialAssessment::class, 'assessment_id');
    }

    public function reinforcer(): BelongsTo
    {
        return $this->belongsTo(ReinforcerCatalog::class, 'reinforcer_id');
    }
}

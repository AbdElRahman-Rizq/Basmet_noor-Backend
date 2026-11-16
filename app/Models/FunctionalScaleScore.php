<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FunctionalScaleScore extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'assessment_id',
        'scale_id',
        'scale_name_snapshot',
        'score',
        'specialist_comment',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
        ];
    }

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(InitialAssessment::class, 'assessment_id');
    }

    public function scale(): BelongsTo
    {
        return $this->belongsTo(FunctionalScale::class, 'scale_id');
    }
}

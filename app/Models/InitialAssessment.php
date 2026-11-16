<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InitialAssessment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'child_id',
        'assessment_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'assessment_date' => 'date',
        ];
    }

    public function child(): BelongsTo
    {
        return $this->belongsTo(Child::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'assessment_id');
    }

    public function scaleScores(): HasMany
    {
        return $this->hasMany(FunctionalScaleScore::class, 'assessment_id');
    }

    public function reinforcers(): HasMany
    {
        return $this->hasMany(AssessmentReinforcer::class, 'assessment_id');
    }
}

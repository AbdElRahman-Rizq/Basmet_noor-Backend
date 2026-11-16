<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Child extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'parent_id',
        'child_code',
        'sequence_number',
        'age',
        'age_recorded_at',
        'nationality',
        'neighborhood',
        'initial_problem',
        'knowledge_source_id',
        'identity_verified',
    ];

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'age' => 'integer',
            'age_recorded_at' => 'date',
            'identity_verified' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ParentProfile::class, 'parent_id');
    }

    public function knowledgeSource(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class);
    }

    public function cases(): HasMany
    {
        return $this->hasMany(CaseFile::class);
    }

    public function initialAssessment(): HasMany
    {
        return $this->hasMany(InitialAssessment::class);
    }

    public function consents(): HasMany
    {
        return $this->hasMany(ChildConsent::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssessmentQuestion extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'section_name',
        'question_text',
        'question_type',
        'options',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AssessmentAnswer::class, 'question_id');
    }
}

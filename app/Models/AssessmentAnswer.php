<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentAnswer extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'assessment_id',
        'question_id',
        'question_text_snapshot',
        'section_name_snapshot',
        'question_type_snapshot',
        'answer_value',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(InitialAssessment::class, 'assessment_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(AssessmentQuestion::class, 'question_id');
    }
}

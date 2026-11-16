<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Report extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'case_id',
        'specialist_id',
        'session_date',
        'file_path',
        'file_original_name',
        'file_mime',
        'file_size',
        'file_checksum',
        'visible_to_parent',
    ];

    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'file_size' => 'integer',
            'visible_to_parent' => 'boolean',
        ];
    }

    public function caseFile(): BelongsTo
    {
        return $this->belongsTo(CaseFile::class, 'case_id');
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class);
    }

    public function progressHistory(): HasMany
    {
        return $this->hasMany(GoalProgressHistory::class);
    }
}

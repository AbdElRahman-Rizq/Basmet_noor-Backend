<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PlanSpecialist extends Pivot
{
    protected $table = 'plan_specialists';

    public $incrementing = false;

    public $timestamps = false;

    public function plan(): BelongsTo
    {
        return $this->belongsTo(TreatmentPlan::class, 'plan_id');
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class, 'specialist_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanCompletion extends Model
{
    protected $fillable = [
        'daily_plan_id',
        'user_id',
        'completion_date',
        'is_completed',
        'checklist_completions'
    ];

    protected $casts = [
        'completion_date' => 'date',
        'is_completed' => 'boolean',
        'checklist_completions' => 'array'
    ];

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
} 
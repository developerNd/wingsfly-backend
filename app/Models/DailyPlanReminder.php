<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyPlanReminder extends Model
{
    protected $fillable = [
        'daily_plan_id',
        'reminder_enabled',
        'reminder_time',
        'reminder_type',
        'reminder_schedule',
        'selected_week_days',
        'days_before_count',
        'hours_before_count'
    ];

    protected $casts = [
        'reminder_enabled' => 'boolean',
        'reminder_time' => 'datetime',
        'selected_week_days' => 'array',
        'days_before_count' => 'integer',
        'hours_before_count' => 'integer'
    ];

    public function dailyPlan(): BelongsTo
    {
        return $this->belongsTo(DailyPlan::class);
    }
}
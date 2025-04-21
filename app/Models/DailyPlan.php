<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DailyPlan extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'task_type',
        'gender',
        'evaluation_type',
        'habit',
        'description',
        'frequency',
        'selected_days',
        'is_flexible',
        'start_date',
        'end_date',
        'duration',
        'priority',
        'block_time',
        'pomodoro',
        'checklist_items'
    ];

    protected $casts = [
        'selected_days' => 'array',
        'is_flexible' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'duration' => 'integer',
        'block_time' => 'array',
        'pomodoro' => 'integer',
        'checklist_items' => 'array'
    ];

    public function reminder(): HasOne
    {
        return $this->hasOne(DailyPlanReminder::class);
    }
}


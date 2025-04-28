<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyPlan extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'task_type',
        'gender',
        'evaluation_type',
        'numeric_condition',
        'numeric_value',
        'numeric_unit',
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
        'checklist_items',
        'additional_data',
        'repetition_data',
        'target',
        'selected_unit',
        'add_to_calendar',
        'add_reminder',
        'add_pomodoro',
        'status'
    ];

    protected $casts = [
        'selected_days' => 'array',
        'is_flexible' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'duration' => 'integer',
        'block_time' => 'array',
        'pomodoro' => 'integer',
        'checklist_items' => 'array',
        'additional_data' => 'array',
        'repetition_data' => 'array',
        'add_to_calendar' => 'boolean',
        'add_reminder' => 'boolean',
        'add_pomodoro' => 'boolean'
    ];

    public function reminder(): HasOne
    {
        return $this->hasOne(DailyPlanReminder::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(DailyPlanCompletion::class);
    }
}


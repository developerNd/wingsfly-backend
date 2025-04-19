<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class RecurringGoal extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'category',
        'color',
        'priority',
        'is_recurring',
        'recurrence_type',
        'recurrence_days',
        'repetition',
        'target',
        'selectedUnit',
        'blockTime',
        'duration',
        'start_time',
        'end_time',
        'duration_minutes',
        'add_to_calendar',
        'add_reminder',
        'add_pomodoro',
        'checklist',
        'start_date',
        'end_date',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'is_recurring' => 'boolean',
        'recurrence_days' => 'array',
        'repetition' => 'array',
        'target' => 'string',
        'selectedUnit' => 'string',
        'blockTime' => 'array',
        'duration' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'add_to_calendar' => 'boolean',
        'add_reminder' => 'boolean',
        'add_pomodoro' => 'boolean',
        'checklist' => 'array',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the recurring goal.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
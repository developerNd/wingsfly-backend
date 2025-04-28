<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\DailyPlanReminder;
use App\Models\DailyPlanCompletion;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DailyPlanController extends Controller
{
    public function index(Request $request)
    {
        $query = DailyPlan::where('user_id', Auth::id());
        // Log::info($query->get());
        Log::info($request->all());
        // Get date from request
        $date = $request->get('date');
        if ($date) {
            $date = Carbon::parse($date);
            // Log::info('Filtering for date: ' . $date->format('Y-m-d'));
            
            // Base query for tasks that should appear on this date
            $query->where(function($q) use ($date) {
                // Tasks that start on or before this date
                $q->where('start_date', '<=', $date);
                
                // And either have no end date or end after this date
                $q->where(function($q2) use ($date) {
                    $q2->whereNull('end_date')
                        ->orWhere('end_date', '>=', $date);
                });
                
                // Handle repetition patterns
                $q->where(function($q3) use ($date) {
                    // Daily repetition
                    $q3->where('frequency', 'every-day')
                        // Weekly repetition on same day of week
                        ->orWhere(function($q4) use ($date) {
                            $q4->where('frequency', 'specific-days-week')
                                ->whereJsonContains('selected_days', $date->dayOfWeek);
                        })
                        // Monthly repetition on same day of month
                        ->orWhere(function($q5) use ($date) {
                            $q5->where('frequency', 'specific-days-month')
                                ->whereJsonContains('selected_days', $date->day);
                        })
                        // Yearly repetition on same day of year
                        ->orWhere(function($q6) use ($date) {
                            $q6->where('frequency', 'specific-days-year')
                                ->whereJsonContains('selected_days', $date->dayOfYear);
                        })
                        // Some days period (handle this based on your specific requirements)
                        ->orWhere(function($q7) use ($date) {
                            $q7->where('frequency', 'some-days-period');
                            // Add logic for some-days-period if needed
                        })
                        // Repeat (handle this based on your specific requirements)
                        ->orWhere(function($q8) use ($date) {
                            $q8->where('frequency', 'repeat');
                            // Add logic for repeat if needed
                        })
                        // Handle repetition data from the JSON field
                        ->orWhere(function($q9) use ($date) {
                            $q9->whereNotNull('repetition_data')
                                ->whereJsonContains('repetition_data->isRecurring', true)
                                ->where(function($q10) use ($date) {
                                    // Daily repetition
                                    $q10->whereJsonContains('repetition_data->selectedOption', 'Daily')
                                        // Weekly repetition on same day of week
                                        ->orWhere(function($q11) use ($date) {
                                            $q11->whereJsonContains('repetition_data->selectedOption', 'Weekly')
                                                ->where(function($q12) use ($date) {
                                                    $daysMap = ['Sun' => 0, 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6];
                                                    $dayName = array_search($date->dayOfWeek, $daysMap);
                                                    $q12->whereJsonContains('repetition_data->selectedDays->' . $dayName, true);
                                                });
                                        })
                                        // Monthly repetition on same day of month
                                        ->orWhere(function($q13) use ($date) {
                                            $q13->whereJsonContains('repetition_data->selectedOption', 'Monthly')
                                                ->whereJsonContains('repetition_data->selectedDate', $date->format('Y-m-d'));
                                        })
                                        // Yearly repetition on same day of year
                                        ->orWhere(function($q14) use ($date) {
                                            $q14->whereJsonContains('repetition_data->selectedOption', 'Yearly')
                                                ->whereJsonContains('repetition_data->selectedDate', $date->format('Y-m-d'));
                                        });
                                });
                        });
                });
            });
            
            // Handle flexible timing
            $query->orWhere('is_flexible', true);
            
            // Also include tasks that have the exact start_date matching the requested date
            $query->orWhere('start_date', $date->format('Y-m-d'));
            
            // Log the SQL query for debugging
            // Log::info('SQL Query: ' . $query->toSql());
            // Log::info('SQL Bindings: ' . json_encode($query->getBindings()));
        }

        $plans = $query->get();
        // Log::info('Found ' . $plans->count() . ' plans');
        
        // Add completion status for the requested date
        if ($date) {
            $completionDate = $date->format('Y-m-d');
            
            foreach ($plans as $plan) {
                $completion = DailyPlanCompletion::where('daily_plan_id', $plan->id)
                    ->where('completion_date', $completionDate)
                    ->first();
                
                // Update the existing isCompleted field instead of adding a new is_completed field
                $plan->isCompleted = $completion ? $completion->is_completed : false;
                
                // If the plan has checklist items, add their completion status
                if ($plan->checklist_items && $completion && $completion->checklist_completions) {
                    // Create a new array to store the updated checklist items
                    $updatedChecklistItems = [];
                    
                    foreach ($plan->checklist_items as $item) {
                        $itemId = $item['id'];
                        $item['completed'] = isset($completion->checklist_completions[$itemId]) ? 
                            $completion->checklist_completions[$itemId] : false;
                        
                        $updatedChecklistItems[] = $item;
                    }
                    
                    // Assign the updated array back to the plan
                    $plan->checklist_items = $updatedChecklistItems;
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $plans
        ]);
    }

    public function store(Request $request)
    {
        Log::info('DailyplanController@store', ['request' => $request->all()]);
        
        // Pre-process the time fields to ensure they're in the correct format
        if ($request->has('block_start_time')) {
            try {
                $time = \Carbon\Carbon::createFromFormat('g:i A', $request->block_start_time);
                $request->merge(['block_start_time' => $time->format('h:i A')]);
            } catch (\Exception $e) {
                // If the format is already correct or can't be parsed, leave it as is
            }
        }
        
        if ($request->has('block_end_time')) {
            try {
                $time = \Carbon\Carbon::createFromFormat('g:i A', $request->block_end_time);
                $request->merge(['block_end_time' => $time->format('h:i A')]);
            } catch (\Exception $e) {
                // If the format is already correct or can't be parsed, leave it as is
            }
        }
        
        $validator = Validator::make($request->all(), [
            'category' => 'required|string',
            'task_type' => 'required|string',
            'gender' => 'required|in:male,female',
            'evaluation_type' => 'required|string',
            'numeric_condition' => 'nullable|string',
            'numeric_value' => 'nullable|numeric',
            'numeric_unit' => 'nullable|string',
            'habit' => 'nullable|string',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'note' => 'nullable|string',
            'frequency' => 'nullable|in:every-day,specific-days-week,specific-days-month,specific-days-year,some-days-period,repeat',
            'selected_days' => 'nullable|array',
            'is_flexible' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'duration' => 'nullable|integer',
            'priority' => 'required|in:Must,Should,Could,Would,Important',
            'block_time' => 'nullable|integer',
            'block_start_time' => 'nullable|date_format:h:i A',
            'block_end_time' => 'nullable|date_format:h:i A',
            'pomodoro' => 'nullable|integer',
            'reminder' => 'nullable|boolean',
            'reminder_time' => 'required_if:reminder,true|date_format:H:i',
            'reminder_type' => 'required_if:reminder,true|in:dont-remind,notification,alarm',
            'reminder_schedule' => 'required_if:reminder,true|in:always-enabled,specific-days,days-before',
            'selected_week_days' => 'required_if:reminder_schedule,specific-days|array',
            'days_before_count' => 'required_if:reminder_schedule,days-before|integer',
            'hours_before_count' => 'nullable|integer',
            'checklist' => 'nullable|array',
            'checklist.items' => 'nullable|array',
            'checklist.successCondition' => 'nullable|string',
            'checklist.customCount' => 'nullable|integer',
            'checklist.note' => 'nullable|string',
            'checklist_items' => 'nullable|array',
            'target' => 'nullable|string',
            'selectedUnit' => 'nullable|string',
            'blockTime' => 'nullable|array',
            'addToCalendar' => 'nullable|boolean',
            'addReminder' => 'nullable|boolean',
            'addPomodoro' => 'nullable|boolean',
            'status' => 'nullable|string',
            'completion_status' => 'nullable|string',
            'is_pending' => 'nullable|boolean',
            'linked_goal' => 'nullable|string',
            'type' => 'nullable|string',
            'repetition' => 'nullable|array',
            'repetition.isRecurring' => 'nullable|boolean',
            'repetition.selectedOption' => 'nullable|string|in:Daily,Weekly,Monthly,Yearly,One-Time',
            'repetition.selectedDate' => 'nullable|date',
            'repetition.selectedDays' => 'nullable|array',
            'repetition.timesPerDay' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            // Process checklist items if provided
            $checklistItems = [];
            $checklistMetadata = null;
            
            // Check if checklist items are provided directly in the request
            if ($request->has('checklist_items') && is_array($request->checklist_items)) {
                foreach ($request->checklist_items as $item) {
                    $checklistItems[] = [
                        'id' => $item['id'],
                        'text' => $item['text'],
                        'completed' => $item['completed'] ?? false,
                        'evaluationType' => $item['evaluationType'] ?? 'yesno',
                        'duration' => $item['duration'] ?? null,
                        'usePomodoro' => $item['usePomodoro'] ?? false,
                        'numeric_condition' => $item['numeric_condition'] ?? null,
                        'numeric_value' => $item['numeric_value'] ?? null,
                        'numeric_unit' => $item['numeric_unit'] ?? null
                    ];
                }
                
                // If this is a numeric evaluation type and we have checklist items,
                // extract the numeric condition, value, and unit from the first item
                if ($request->evaluation_type === 'numeric' && !empty($checklistItems)) {
                    $firstItem = $checklistItems[0];
                    $numericCondition = $firstItem['numeric_condition'] ?? null;
                    $numericValue = $firstItem['numeric_value'] ?? null;
                    $numericUnit = $firstItem['numeric_unit'] ?? null;
                }
            }
            // Fallback to the old format if checklist_items is not provided
            else if ($request->has('checklist')) {
                // Store checklist metadata
                $checklistMetadata = [
                    'successCondition' => $request->input('checklist.successCondition'),
                    'customCount' => $request->input('checklist.customCount'),
                    'note' => $request->input('checklist.note')
                ];
                
                // Process checklist items
                if (isset($request->checklist['items'])) {
                    foreach ($request->checklist['items'] as $item) {
                        $checklistItems[] = [
                            'id' => $item['id'],
                            'text' => $item['text'],
                            'completed' => $item['completed'] ?? false,
                            'evaluationType' => $item['evaluationType'] ?? 'yesno',
                            'duration' => $item['duration'] ?? null,
                            'usePomodoro' => $item['usePomodoro'] ?? false,
                            'numeric_condition' => $item['numeric_condition'] ?? null,
                            'numeric_value' => $item['numeric_value'] ?? null,
                            'numeric_unit' => $item['numeric_unit'] ?? null
                        ];
                    }
                    
                    // If this is a numeric evaluation type and we have checklist items,
                    // extract the numeric condition, value, and unit from the first item
                    if ($request->evaluation_type === 'numeric' && !empty($checklistItems)) {
                        $firstItem = $checklistItems[0];
                        $numericCondition = $firstItem['numeric_condition'] ?? null;
                        $numericValue = $firstItem['numeric_value'] ?? null;
                        $numericUnit = $firstItem['numeric_unit'] ?? null;
                    }
                }
            }

            // Process block time data
            $blockTimeData = null;
            if ($request->has('blockTime')) {
                // Transform the complex blockTime object into a simpler format
                $startTime = $request->input('blockTime.startTime');
                $endTime = $request->input('blockTime.endTime');
                
                // Format start time
                $formattedStartTime = '';
                if (isset($startTime['hour']) && isset($startTime['minute']) && isset($startTime['period'])) {
                    $formattedStartTime = sprintf(
                        '%02d:%02d %s',
                        $startTime['hour'],
                        $startTime['minute'],
                        $startTime['period']
                    );
                }
                
                // Format end time
                $formattedEndTime = '';
                if (isset($endTime['hour']) && isset($endTime['minute']) && isset($endTime['period'])) {
                    $formattedEndTime = sprintf(
                        '%02d:%02d %s',
                        $endTime['hour'],
                        $endTime['minute'],
                        $endTime['period']
                    );
                }
                
                $blockTimeData = [
                    'start_time' => $formattedStartTime,
                    'end_time' => $formattedEndTime
                ];
            } else if ($request->has('block_start_time') || $request->has('block_end_time')) {
                $blockTimeData = [
                    'start_time' => $request->input('block_start_time'),
                    'end_time' => $request->input('block_end_time')
                ];
            }

            // Process repetition data and set frequency and selected_days
            $repetitionData = null;
            $frequency = $request->frequency;
            $selectedDays = $request->selected_days;
            
            if ($request->has('repetition') && $request->repetition !== 'undefined') {
                $repetitionData = [
                    'isRecurring' => $request->input('repetition.isRecurring'),
                    'selectedOption' => $request->input('repetition.selectedOption'),
                    'selectedDate' => $request->input('repetition.selectedDate'),
                    'selectedDays' => $request->input('repetition.selectedDays'),
                    'timesPerDay' => $request->input('repetition.timesPerDay')
                ];
                
                // Set frequency based on repetition data
                if ($request->input('repetition.isRecurring')) {
                    $selectedOption = $request->input('repetition.selectedOption');
                    
                    switch ($selectedOption) {
                        case 'Daily':
                            $frequency = 'every-day';
                            break;
                        case 'Weekly':
                            $frequency = 'specific-days-week';
                            // Convert selected days to day numbers (0-6)
                            $selectedDays = [];
                            $daysMap = ['Sun' => 0, 'Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6];
                            foreach ($request->input('repetition.selectedDays') as $day => $isSelected) {
                                if ($isSelected) {
                                    $selectedDays[] = $daysMap[$day];
                                }
                            }
                            break;
                        case 'Monthly':
                            $frequency = 'specific-days-month';
                            // Extract day of month from selectedDate
                            $selectedDate = Carbon::parse($request->input('repetition.selectedDate'));
                            $selectedDays = [$selectedDate->day];
                            break;
                        case 'Yearly':
                            $frequency = 'specific-days-year';
                            // Extract day of year from selectedDate
                            $selectedDate = Carbon::parse($request->input('repetition.selectedDate'));
                            $selectedDays = [$selectedDate->dayOfYear];
                            break;
                        default:
                            // Keep the original frequency if no match
                            break;
                    }
                } else if ($request->input('repetition.selectedOption') === 'One-Time' && $request->input('repetition.selectedDate')) {
                    // Handle One-Time option with selected date
                    $selectedDate = Carbon::parse($request->input('repetition.selectedDate'));
                    $start_date = $selectedDate->format('Y-m-d');
                    $frequency = 'repeat'; // Use 'repeat' frequency for one-time tasks
                }
            }

            // Store only truly additional data in the additional_data field
            // Avoid duplicating data that's already stored in dedicated columns
            $additionalData = [
                // Only include data that doesn't have a dedicated column
                'note' => $request->note,
                'addToCalendar' => $request->addToCalendar,
                'addReminder' => $request->addReminder,
                'addPomodoro' => $request->addPomodoro,
                'checklist_metadata' => $checklistMetadata
            ];

            // Determine the habit value - use title if habit is not provided
            $habitValue = $request->habit;
            if (empty($habitValue) && !empty($request->title)) {
                $habitValue = $request->title;
            }

            // Determine the description value - use note if description is not provided
            $descriptionValue = $request->description;
            if (empty($descriptionValue) && !empty($request->note)) {
                $descriptionValue = $request->note;
            }

            $dailyPlan = DailyPlan::create([
                'user_id' => Auth::id(),
                'category' => $request->category,
                'task_type' => $request->task_type,
                'gender' => $request->gender,
                'evaluation_type' => $request->evaluation_type,
                'numeric_condition' => $request->numeric_condition ?? $numericCondition ?? null,
                'numeric_value' => $request->numeric_value ?? $numericValue ?? null,
                'numeric_unit' => $request->numeric_unit ?? $numericUnit ?? null,
                'habit' => $habitValue,
                'description' => $descriptionValue,
                'frequency' => $frequency,
                'selected_days' => $selectedDays,
                'is_flexible' => $request->is_flexible ?? false,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'duration' => $request->duration,
                'priority' => $request->priority,
                'block_time' => $blockTimeData,
                'pomodoro' => $request->pomodoro ?? 0,
                'checklist_items' => $checklistItems,
                'additional_data' => $additionalData,
                'repetition_data' => $repetitionData,
                'target' => $request->target,
                'selected_unit' => $request->selectedUnit,
                'add_to_calendar' => $request->addToCalendar,
                'add_reminder' => $request->addReminder,
                'add_pomodoro' => $request->addPomodoro,
                'status' => $request->status
            ]);

            if ($request->reminder || $request->addReminder) {
                DailyPlanReminder::create([
                    'daily_plan_id' => $dailyPlan->id,
                    'reminder_enabled' => true,
                    'reminder_time' => $request->reminder_time,
                    'reminder_type' => $request->reminder_type ?? 'notification',
                    'reminder_schedule' => $request->reminder_schedule,
                    'selected_week_days' => $request->selected_week_days,
                    'days_before_count' => $request->days_before_count,
                    'hours_before_count' => $request->hours_before_count
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Daily plan created successfully',
                'data' => $dailyPlan->load('reminder')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error creating daily plan',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(DailyPlan $dailyPlan)
    {
        return response()->json([
            'data' => $dailyPlan->load('reminder')
        ]);
    }

    public function update(Request $request, DailyPlan $dailyPlan)
    {
        // Similar validation as store method
        // Update logic here
    }

    public function destroy(DailyPlan $dailyPlan)
    {
        $dailyPlan->delete();
        return response()->json(['message' => 'Daily plan deleted successfully']);
    }

    /**
     * Update the completion status of a task in a daily plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateTaskCompletionStatus(Request $request, $id)
    {
        Log::info('DailyplanController@updateTaskCompletionStatus', ['request' => $request->all(), 'id' => $id]);
        
        $validator = Validator::make($request->all(), [
            'completed' => 'required|boolean',
            'date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dailyPlan = DailyPlan::where('user_id', Auth::id())->findOrFail($id);
        
        // Get the date from the request or use today
        $date = $request->has('date') ? Carbon::parse($request->date) : Carbon::today();
        $dateString = $date->format('Y-m-d');
        
        // Get or create a completion record for the specified date
        $completion = DailyPlanCompletion::firstOrCreate(
            [
                'daily_plan_id' => $dailyPlan->id,
                'user_id' => Auth::id(),
                'completion_date' => $dateString
            ],
            [
                'is_completed' => false,
                'checklist_completions' => []
            ]
        );
        
        // Update the completion status
        $completion->is_completed = $request->completed;
        
        // If marking as completed, also mark all checklist items as completed
        if ($request->completed && $dailyPlan->checklist_items) {
            $checklistCompletions = [];
            foreach ($dailyPlan->checklist_items as $item) {
                $checklistCompletions[$item['id']] = true;
            }
            $completion->checklist_completions = $checklistCompletions;
        }
        
        $completion->save();
        
        // Also update the isCompleted field in the daily_plans table
        $dailyPlan->isCompleted = $request->completed;
        $dailyPlan->save();

        return response()->json([
            'message' => 'Task completion status updated successfully',
            'data' => $dailyPlan
        ]);
    }

    public function toggleChecklistItem(Request $request, $id, $itemId)
    {
        Log::info('DailyplanController@toggleChecklistItem', ['request' => $request->all(), 'id' => $id, 'itemId' => $itemId]);
        
        $dailyPlan = DailyPlan::where('user_id', Auth::id())->findOrFail($id);
        
        // Get the checklist items
        $checklistItems = $dailyPlan->checklist_items ?? [];
        
        // Find the item with the specified ID
        $itemFound = false;
        $itemIndex = -1;
        foreach ($checklistItems as $key => $item) {
            if ($item['id'] === $itemId) {
                $itemIndex = $key;
                $itemFound = true;
                break;
            }
        }
        
        if (!$itemFound) {
            return response()->json([
                'message' => 'Checklist item not found',
                'status' => 'error'
            ], 404);
        }
        
        // Get or create a completion record for today
        $today = Carbon::today()->format('Y-m-d');
        $completion = DailyPlanCompletion::firstOrCreate(
            [
                'daily_plan_id' => $dailyPlan->id,
                'user_id' => Auth::id(),
                'completion_date' => $today
            ],
            [
                'is_completed' => false,
                'checklist_completions' => []
            ]
        );
        
        // Toggle the completion status for this item
        $checklistCompletions = $completion->checklist_completions ?? [];
        $currentStatus = $checklistItems[$itemIndex]['completed'];
        $checklistCompletions[$itemId] = !$currentStatus;
        
        // Update the completion record
        $completion->checklist_completions = $checklistCompletions;
        
        // Check if all items are completed
        $allCompleted = true;
        foreach ($checklistItems as $item) {
            $itemId = $item['id'];
            if (!isset($checklistCompletions[$itemId]) || !$checklistCompletions[$itemId]) {
                $allCompleted = false;
                break;
            }
        }
        
        // Update the overall completion status
        $completion->is_completed = $allCompleted;
        $completion->save();
        
        // Update the checklist item in the response
        $checklistItems[$itemIndex]['completed'] = !$currentStatus;
        $dailyPlan->checklist_items = $checklistItems;
        
        // Also update the isCompleted field in the daily_plans table
        $dailyPlan->isCompleted = $allCompleted;
        $dailyPlan->save();
        
        return response()->json([
            'message' => 'Checklist item toggled successfully',
            'data' => $dailyPlan
        ]);
    }

    public function toggleCompletionStatus(Request $request, $id)
    {
        Log::info('DailyplanController@toggleCompletionStatus', ['request' => $request->all(), 'id' => $id]);
        
        $validator = Validator::make($request->all(), [
            'completed' => 'required|boolean',
            'date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $dailyPlan = DailyPlan::where('user_id', Auth::id())->findOrFail($id);
        
        // Get the date from the request or use today
        $date = $request->has('date') ? Carbon::parse($request->date) : Carbon::today();
        $dateString = $date->format('Y-m-d');
        
        // Get or create a completion record for the specified date
        $completion = DailyPlanCompletion::firstOrCreate(
            [
                'daily_plan_id' => $dailyPlan->id,
                'user_id' => Auth::id(),
                'completion_date' => $dateString
            ],
            [
                'is_completed' => false,
                'checklist_completions' => []
            ]
        );
        
        // Toggle the completion status
        $completion->is_completed = $request->completed;
        
        // If marking as completed, also mark all checklist items as completed
        if ($request->completed && $dailyPlan->checklist_items) {
            $checklistCompletions = [];
            foreach ($dailyPlan->checklist_items as $item) {
                $checklistCompletions[$item['id']] = true;
            }
            $completion->checklist_completions = $checklistCompletions;
        }
        
        $completion->save();
        
        return response()->json([
            'message' => 'Completion status updated successfully',
            'data' => $dailyPlan
        ]);
    }
}

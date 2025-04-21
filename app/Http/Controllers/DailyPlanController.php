<?php

namespace App\Http\Controllers;

use App\Models\DailyPlan;
use App\Models\DailyPlanReminder;
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
        $query = DailyPlan::query();

        // Get date from request
        $date = $request->get('date');
        if ($date) {
            $date = Carbon::parse($date);
            
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
                        });
                });
            });
            
            // Handle flexible timing
            $query->orWhere('is_flexible', true);
        }

        $plans = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $plans
        ]);
    }

    public function store(Request $request)
    {
        Log ::info('DailyplanController@store', ['request' => $request->all()]);
        $validator = Validator::make($request->all(), [
            'category' => 'required|string',
            'task_type' => 'required|string',
            'gender' => 'required|in:male,female',
            'evaluation_type' => 'required|string',
            'habit' => 'required|string',
            'description' => 'nullable|string',
            'frequency' => 'nullable|in:every-day,specific-days-week,specific-days-month,specific-days-year,some-days-period,repeat',
            'selected_days' => 'nullable|array',
            'is_flexible' => 'required|boolean',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'duration' => 'nullable|integer',
            'priority' => 'required|in:Must,Should,Could,Would,Important',
            'block_time' => 'nullable|integer',
            'block_start_time' => 'nullable|date_format:h:i A',
            'block_end_time' => 'nullable|date_format:h:i A',
            'pomodoro' => 'required|integer',
            'reminder' => 'required|boolean',
            'reminder_time' => 'required_if:reminder,true|date_format:H:i',
            'reminder_type' => 'required_if:reminder,true|in:dont-remind,notification,alarm',
            'reminder_schedule' => 'required_if:reminder,true|in:always-enabled,specific-days,days-before',
            'selected_week_days' => 'required_if:reminder_schedule,specific-days|array',
            'days_before_count' => 'required_if:reminder_schedule,days-before|integer',
            'hours_before_count' => 'nullable|integer',
            'checklist_items' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            DB::beginTransaction();

            $dailyPlan = DailyPlan::create([
                'user_id' => Auth::id(),
                'category' => $request->category,
                'task_type' => $request->task_type,
                'gender' => $request->gender,
                'evaluation_type' => $request->evaluation_type,
                'habit' => $request->habit,
                'description' => $request->description,
                'frequency' => $request->frequency,
                'selected_days' => $request->selected_days,
                'is_flexible' => $request->is_flexible,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'duration' => $request->duration,
                'priority' => $request->priority,
                'block_time' => [
                    'start_time' => $request->block_start_time,
                    'end_time' => $request->block_end_time
                ],
                'pomodoro' => $request->pomodoro,
                'checklist_items' => $request->checklist_items
            ]);

            if ($request->reminder) {
                DailyPlanReminder::create([
                    'daily_plan_id' => $dailyPlan->id,
                    'reminder_enabled' => true,
                    'reminder_time' => $request->reminder_time,
                    'reminder_type' => $request->reminder_type,
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
            // 'date' => 'required|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $dailyPlan = DailyPlan::where('user_id', Auth::id())->findOrFail($id);
        
        // Get the current completion status or initialize if not exists
        // $completionStatus = $dailyPlan->completion_status ?? [];
        
        // Format the date as Y-m-d
        // $formattedDate = date('Y-m-d', strtotime($request->date));
        
        // // Update the completion status for the specified date
        // $completionStatus[$formattedDate] = $request->completed;
        
        // Update the daily plan with the new completion status
        $dailyPlan->isCompleted = true;
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
        foreach ($checklistItems as $key => $item) {
            if ($item['id'] === $itemId) {
                // Toggle the completed status
                $checklistItems[$key]['completed'] = !$item['completed'];
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
        
        // Update the checklist items
        $dailyPlan->checklist_items = $checklistItems;
        $dailyPlan->save();
        
        return response()->json([
            'message' => 'Checklist item toggled successfully',
            'data' => $dailyPlan
        ]);
    }
}

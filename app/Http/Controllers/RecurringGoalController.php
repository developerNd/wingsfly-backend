<?php

namespace App\Http\Controllers;

use App\Models\RecurringGoal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class RecurringGoalController extends Controller
{
    /**
     * Display a listing of the recurring goals.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $query = RecurringGoal::with('checklist.items');

        // Get date from request
        $date = $request->get('date');
        if ($date) {
            $date = Carbon::parse($date);
            
            // Base query for goals that should appear on this date
            $query->where(function($q) use ($date, $request) {
                // Goals that start on or before this date
                $q->where('start_date', '<=', $date);
                
                // And either have no end date or end after this date
                $q->where(function($q2) use ($date) {
                    $q2->whereNull('end_date')
                        ->orWhere('end_date', '>=', $date);
                });
                
                // Handle repetition patterns
                if ($request->get('includeRepetitive', true)) {
                    $q->where(function($q3) use ($date) {
                        // Daily repetition
                        $q3->where('repetition', 'daily')
                            // Weekly repetition on same day of week
                            ->orWhere(function($q4) use ($date) {
                                $q4->where('repetition', 'weekly')
                                    ->whereRaw('DAYOFWEEK(start_date) = ?', [$date->dayOfWeek]);
                            })
                            // Monthly repetition on same day of month
                            ->orWhere(function($q5) use ($date) {
                                $q5->where('repetition', 'monthly')
                                    ->whereRaw('DAY(start_date) = ?', [$date->day]);
                            });
                    });
                }
            });
            
            // Handle flexible timing
            if ($request->get('includeFlexible', true)) {
                $query->orWhere('is_flexible', true);
            }
        }

        $goals = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $goals
        ]);
    }

    /**
     * Store a newly created recurring goal in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        Log::info($request->all());
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'note' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'priority' => 'nullable|string|max:255',
            'is_recurring' => 'boolean',
            'repetition' => 'nullable|array',
            'repetition.isRecurring' => 'required|boolean',
            'repetition.selectedOption' => 'required_if:repetition.isRecurring,true|string|in:Daily,Weekly,Monthly,Specific Days,Periodic,Yearly',
            'repetition.selectedDate' => 'required|date',
            'repetition.selectedDays' => 'required_if:repetition.selectedOption,Daily,Weekly,Specific Days|array',
            'repetition.timesPerDay' => 'required_if:repetition.selectedOption,Daily,Weekly|integer|min:1',
            'repetition.periodicValue' => 'required_if:repetition.selectedOption,Monthly,Periodic|integer|min:1',
            'repetition.periodicUnit' => 'required_if:repetition.selectedOption,Monthly,Periodic|integer|min:1',
            'repetition.selectedMonth' => 'required_if:repetition.selectedOption,Yearly|string',
            'repetition.weekOfMonth' => 'required_if:repetition.selectedOption,Yearly|string',
            'repetition.dayOfWeek' => 'required_if:repetition.selectedOption,Yearly|string',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s',
            'duration_minutes' => 'nullable|integer',
            'add_to_calendar' => 'boolean',
            'add_reminder' => 'boolean',
            'add_pomodoro' => 'boolean',
            'checklist' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'boolean',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
    
        // Process the repetition data based on the type
        $data = $request->all();
        if (isset($data['repetition'])) {
            if (!$data['repetition']['isRecurring']) {
                // For one-time goals, only keep isRecurring and selectedDate
                $data['repetition'] = [
                    'isRecurring' => false,
                    'selectedDate' => $data['repetition']['selectedDate']
                ];
            } else {
                // For recurring goals, process based on selectedOption
                $repetition = $data['repetition'];
                $processedRepetition = [
                    'isRecurring' => true,
                    'selectedOption' => $repetition['selectedOption'],
                    'selectedDate' => $repetition['selectedDate']
                ];
    
                switch ($repetition['selectedOption']) {
                    case 'Daily':
                    case 'Weekly':
                        $processedRepetition['selectedDays'] = $repetition['selectedDays'];
                        $processedRepetition['timesPerDay'] = $repetition['timesPerDay'];
                        break;
                    case 'Monthly':
                    case 'Periodic':
                        $processedRepetition['periodicValue'] = $repetition['periodicValue'];
                        $processedRepetition['periodicUnit'] = $repetition['periodicUnit'];
                        break;
                    case 'Specific Days':
                        $processedRepetition['selectedDays'] = $repetition['selectedDays'];
                        break;
                    case 'Yearly':
                        $processedRepetition['selectedMonth'] = $repetition['selectedMonth'];
                        $processedRepetition['weekOfMonth'] = $repetition['weekOfMonth'];
                        $processedRepetition['dayOfWeek'] = $repetition['dayOfWeek'];
                        break;
                }
                $data['repetition'] = $processedRepetition;
            }
        }
    
        $recurringGoal = new RecurringGoal($data);
        $recurringGoal->user_id = Auth::id();
        $recurringGoal->save();
    
        return response()->json([
            'status' => 'success',
            'message' => 'Recurring goal created successfully',
            'data' => $recurringGoal
        ], 201);
    }

    /**
     * Display the specified recurring goal.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $recurringGoal = RecurringGoal::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $recurringGoal
        ]);
    }

    /**
     * Update the specified recurring goal in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $recurringGoal = RecurringGoal::where('user_id', Auth::id())
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'priority' => 'nullable|string|max:255',
            'is_recurring' => 'boolean',
            'recurrence_type' => 'nullable|string|max:255',
            'recurrence_days' => 'nullable|array',
            'start_time' => 'nullable|date_format:H:i:s',
            'end_time' => 'nullable|date_format:H:i:s',
            'duration_minutes' => 'nullable|integer',
            'add_to_calendar' => 'boolean',
            'add_reminder' => 'boolean',
            'add_pomodoro' => 'boolean',
            'checklist' => 'nullable|array',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $recurringGoal->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Recurring goal updated successfully',
            'data' => $recurringGoal
        ]);
    }

    /**
     * Remove the specified recurring goal from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $recurringGoal = RecurringGoal::where('user_id', Auth::id())
            ->findOrFail($id);

        $recurringGoal->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Recurring goal deleted successfully'
        ]);
    }

    /**
     * Add a new checklist item to a recurring goal.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addChecklistItem(Request $request, $id)
    {
        $recurringGoal = RecurringGoal::where('user_id', Auth::id())
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get current checklist or initialize if not exists
        $checklist = $recurringGoal->checklist ?? ['items' => [], 'successCondition' => 'all', 'note' => null];
        
        // Generate a unique ID for the new item
        $newItemId = (string) time() . rand(1000, 9999);
        
        // Add the new item to the checklist
        $checklist['items'][] = [
            'id' => $newItemId,
            'text' => $request->text,
            'completed' => false
        ];
        
        // Update the recurring goal with the new checklist
        $recurringGoal->checklist = $checklist;
        $recurringGoal->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Checklist item added successfully',
            'data' => $recurringGoal
        ]);
    }

    /**
     * Delete a checklist item from a recurring goal.
     *
     * @param  int  $id
     * @param  string  $itemId
     * @return \Illuminate\Http\Response
     */
    public function deleteChecklistItem($id, $itemId)
    {
        $recurringGoal = RecurringGoal::where('user_id', Auth::id())
            ->findOrFail($id);

        // Check if checklist exists
        if (!$recurringGoal->checklist || !isset($recurringGoal->checklist['items'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Checklist not found'
            ], 404);
        }

        // Get the current checklist
        $checklist = $recurringGoal->checklist;
        
        // Find and remove the item with the specified ID
        $items = collect($checklist['items']);
        $filteredItems = $items->filter(function ($item) use ($itemId) {
            return $item['id'] !== $itemId;
        })->values()->all();

        // Update the checklist with the filtered items
        $checklist['items'] = $filteredItems;
        
        // Update the model with the new checklist
        $recurringGoal->checklist = $checklist;
        $recurringGoal->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Checklist item deleted successfully',
            'data' => $recurringGoal
        ]);
    }

    /**
     * Mark a checklist item as completed or not completed.
     *
     * @param  int  $id
     * @param  string  $itemId
     * @return \Illuminate\Http\Response
     */
    public function markChecklistItemCompleted($id, $itemId)
    {
        $recurringGoal = RecurringGoal::where('user_id', Auth::id())
            ->findOrFail($id);

        // Check if checklist exists
        if (!$recurringGoal->checklist || !isset($recurringGoal->checklist['items'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Checklist not found'
            ], 404);
        }

        // Get the current checklist
        $checklist = $recurringGoal->checklist;
        $items = $checklist['items'];
        
        // Find the item with the specified ID
        $itemFound = false;
        foreach ($items as $key => $item) {
            if ($item['id'] === $itemId) {
                // Toggle the completed status
                $items[$key]['completed'] = !$item['completed'];
                $itemFound = true;
                break;
            }
        }

        if (!$itemFound) {
            return response()->json([
                'status' => 'error',
                'message' => 'Checklist item not found'
            ], 404);
        }
        
        // Update the checklist with the modified items
        $checklist['items'] = $items;
        
        // Update the model with the new checklist
        $recurringGoal->checklist = $checklist;
        $recurringGoal->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Checklist item status updated successfully',
            'data' => $recurringGoal
        ]);
    }

    /**
     * Update the success condition of a checklist.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateChecklistSuccessCondition(Request $request, $id)
    {
        Log::info($request->all());
        $recurringGoal = RecurringGoal::where('user_id', Auth::id())
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'successCondition' => 'required|string|in:all,any,number,1,2,3,4,5,6,7,8,9,10',
            'number' => 'required_if:successCondition,number|integer|min:1',
            'note' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if checklist exists
        if (!$recurringGoal->checklist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Checklist not found'
            ], 404);
        }

        // Get the current checklist
        $checklist = $recurringGoal->checklist;
        
        // Update the success condition
        $checklist['successCondition'] = $request->successCondition;
        
        // If success condition is 'number' or a numeric value, add the number field
        if ($request->successCondition === 'number' || is_numeric($request->successCondition)) {
            $checklist['number'] = is_numeric($request->successCondition) ? (int)$request->successCondition : $request->number;
        } else {
            // Remove the number field if it exists
            unset($checklist['number']);
        }
        
        // Update the note if provided
        if ($request->has('note')) {
            $checklist['note'] = $request->note;
        }
        
        // Update the model with the new checklist
        $recurringGoal->checklist = $checklist;
        $recurringGoal->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Checklist success condition updated successfully',
            'data' => $recurringGoal
        ]);
    }
}
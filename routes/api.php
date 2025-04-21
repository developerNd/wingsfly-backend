<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyPlanController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\RecurringGoalController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


// Google OAuth routes
Route::get('/auth/google', [GoogleAuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'handleGoogleCallback']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::put('/user/gender', [UserController::class, 'updateGender']);
    Route::get('/user/profile', [UserController::class, 'getProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
});

Route::post('/tokens/create', function (Request $request) {
    $token = $request->user()->createToken($request->token_name);
    Log::info($token);
    return ['token' => $token->plainTextToken];
});
// ... existing code ...

// Recurring Goals Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/recurring-goals', [RecurringGoalController::class, 'index']);
    Route::post('/recurring-goals', [RecurringGoalController::class, 'store']);
    Route::get('/recurring-goals/{id}', [RecurringGoalController::class, 'show']);
    Route::put('/recurring-goals/{id}', [RecurringGoalController::class, 'update']);
    Route::delete('/recurring-goals/{id}', [RecurringGoalController::class, 'destroy']);
    
    // New routes for checklist items
    Route::post('/recurring-goals/{id}/checklist-items', [RecurringGoalController::class, 'addChecklistItem']);
    Route::delete('/recurring-goals/{id}/checklist-items/{itemId}', [RecurringGoalController::class, 'deleteChecklistItem']);
    Route::put('/recurring-goals/{id}/checklist-items/{itemId}/toggle', [RecurringGoalController::class, 'markChecklistItemCompleted']);
    
    // Route for updating checklist success condition
    Route::put('/recurring-goals/{id}/checklist/success-condition', [RecurringGoalController::class, 'updateChecklistSuccessCondition']);
});
// Dailyplan Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('daily-plans', DailyPlanController::class);
    
    // Route for updating task completion status
    Route::put('/daily-plans/{id}/completion-status', [DailyPlanController::class, 'updateTaskCompletionStatus']);
    // Route for updating task completion status
    Route::put('/daily-plans/{id}/checklist-items/{itemId}/toggle', [DailyPlanController::class, 'toggleChecklistItem']);
});
// ... existing code ...
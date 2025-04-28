<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            // Add JSON columns for additional data
            $table->json('additional_data')->nullable()->after('checklist_items');
            $table->json('repetition_data')->nullable()->after('additional_data');
            
            // Add columns for target and unit
            $table->string('target')->nullable()->after('repetition_data');
            $table->string('selected_unit')->nullable()->after('target');
            
            // Add columns for calendar, reminder, and pomodoro settings
            $table->boolean('add_to_calendar')->default(false)->after('selected_unit');
            $table->boolean('add_reminder')->default(false)->after('add_to_calendar');
            $table->boolean('add_pomodoro')->default(false)->after('add_reminder');
            
            // Add status column
            $table->string('status')->nullable()->after('add_pomodoro');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropColumn([
                'additional_data',
                'repetition_data',
                'target',
                'selected_unit',
                'add_to_calendar',
                'add_reminder',
                'add_pomodoro',
                'status'
            ]);
        });
    }
};

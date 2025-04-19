<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('category');
            $table->string('task_type');
            $table->enum('gender', ['male', 'female']);
            $table->string('evaluation_type');
            $table->string('habit');
            $table->text('description')->nullable();
            $table->enum('frequency', ['every-day', 'specific-days-week', 'specific-days-month', 'specific-days-year', 'some-days-period', 'repeat']);
            $table->json('selected_days')->nullable();
            $table->boolean('is_flexible')->default(false);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->integer('duration')->nullable();
            $table->enum('priority', ['Must', 'Should', 'Could', 'Would']);
            $table->integer('block_time')->default(0);
            $table->integer('pomodoro')->default(0);
            $table->boolean('reminder')->default(false);
            $table->boolean('completion_status')->default(0);
            $table->timestamps();
        });

        Schema::create('daily_plan_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')->constrained()->onDelete('cascade');
            $table->boolean('reminder_enabled')->default(false);
            $table->time('reminder_time')->nullable();
            $table->enum('reminder_type', ['dont-remind', 'notification', 'alarm']);
            $table->enum('reminder_schedule', ['always-enabled', 'specific-days', 'days-before']);
            $table->json('selected_week_days')->nullable();
            $table->integer('days_before_count')->default(0);
            $table->integer('hours_before_count')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_plan_reminders');
        Schema::dropIfExists('daily_plans');
    }
};
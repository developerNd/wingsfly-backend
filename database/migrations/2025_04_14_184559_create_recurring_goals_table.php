<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecurringGoalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recurring_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('note')->nullable();
            $table->string('category')->nullable();
            $table->string('color')->nullable();
            $table->string('priority')->nullable();
            $table->boolean('is_recurring')->default(true);
            $table->json('repetition')->nullable(); // Will store all repetition data in a structured way
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->boolean('add_to_calendar')->default(false);
            $table->boolean('add_reminder')->default(false);
            $table->boolean('add_pomodoro')->default(false);
            $table->json('checklist')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('recurring_goals');
    }
}
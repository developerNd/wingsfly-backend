<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('daily_plan_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('completion_date');
            $table->boolean('is_completed')->default(false);
            $table->json('checklist_completions')->nullable();
            $table->timestamps();
            
            // Create a unique index to prevent duplicate entries
            $table->unique(['daily_plan_id', 'completion_date']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('daily_plan_completions');
    }
}; 
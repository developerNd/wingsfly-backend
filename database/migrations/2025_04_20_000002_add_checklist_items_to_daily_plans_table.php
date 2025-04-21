<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->json('checklist_items')->nullable()->after('pomodoro');
        });
    }

    public function down()
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropColumn('checklist_items');
        });
    }
}; 
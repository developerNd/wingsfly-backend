<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            // First, drop the existing block_time column
            $table->dropColumn('block_time');
            
            // Then, add the new JSON column
            $table->json('block_time')->nullable()->after('priority');
        });
    }

    public function down()
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            // First, drop the JSON column
            $table->dropColumn('block_time');
            
            // Then, recreate the original integer column
            $table->integer('block_time')->default(0)->after('priority');
        });
    }
}; 
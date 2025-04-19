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
        Schema::table('recurring_goals', function (Blueprint $table) {
            $table->string('target')->nullable();
            $table->string('selectedUnit')->nullable();
            $table->json('blockTime')->nullable();
            $table->json('duration')->nullable();
            $table->boolean('is_flexible')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_goals', function (Blueprint $table) {
            $table->dropColumn('target');
            $table->dropColumn('selectedUnit');
            $table->dropColumn('blockTime');
            $table->dropColumn('duration');
            $table->dropColumn('is_flexible');
        });
    }
};

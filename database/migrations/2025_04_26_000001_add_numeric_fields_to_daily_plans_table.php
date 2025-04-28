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
            // Add columns for numeric evaluation type
            $table->string('numeric_condition')->nullable()->after('evaluation_type');
            $table->decimal('numeric_value', 10, 2)->nullable()->after('numeric_condition');
            $table->string('numeric_unit')->nullable()->after('numeric_value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('daily_plans', function (Blueprint $table) {
            $table->dropColumn([
                'numeric_condition',
                'numeric_value',
                'numeric_unit'
            ]);
        });
    }
}; 
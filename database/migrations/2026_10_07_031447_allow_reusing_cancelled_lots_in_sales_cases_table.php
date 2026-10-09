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
        Schema::table('sales_cases', function (Blueprint $table) {
            $table->dropUnique(['lot_id']);
            $table->index('lot_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_cases', function (Blueprint $table) {
            $table->dropIndex(['lot_id']);
            $table->unique('lot_id');
        });
    }
};

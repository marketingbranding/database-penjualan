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
        Schema::table('sales_cases', function (Blueprint $table): void {
            $table->dropForeign(['lot_id']);
            $table->dropUnique(['lot_id']);
            $table->index('lot_id');
            $table->foreign('lot_id')->references('id')->on('lots')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_cases', function (Blueprint $table): void {
            $table->dropForeign(['lot_id']);
            $table->dropIndex(['lot_id']);
            $table->unique('lot_id');
            $table->foreign('lot_id')->references('id')->on('lots')->restrictOnDelete();
        });
    }
};

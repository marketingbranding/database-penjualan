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
        Schema::table('promotions', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('promo_id');
            $table->boolean('is_global')->default(false)->after('is_active');
            $table->date('start_date')->nullable()->change();
            $table->date('end_date')->nullable()->change();
            $table->index(['is_active', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'start_date', 'end_date']);
            $table->dropColumn(['is_active', 'is_global']);
        });
    }
};

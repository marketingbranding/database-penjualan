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
        Schema::table('sales_agendas', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('sales_case_id')->constrained('leads')->nullOnDelete();
            $table->index(['lead_id', 'agenda_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_agendas', function (Blueprint $table) {
            $table->dropForeign(['lead_id']);
            $table->dropIndex(['lead_id', 'agenda_date']);
            $table->dropColumn('lead_id');
        });
    }
};

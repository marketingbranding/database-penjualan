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
            $table->foreignId('daily_plan_id')->nullable()->after('team_id')->constrained('sales_daily_plans')->nullOnDelete();
            $table->unsignedSmallInteger('duration_minutes')->nullable()->after('agenda_date');
            $table->unsignedInteger('sort_order')->nullable()->after('duration_minutes');
            $table->dateTime('planned_start_at')->nullable()->after('sort_order');
            $table->dateTime('planned_end_at')->nullable()->after('planned_start_at');
            $table->text('plan_detail')->nullable()->after('title');
            $table->unsignedInteger('planned_quantity')->nullable()->after('plan_detail');

            $table->index(['daily_plan_id', 'sort_order']);
            $table->index(['agenda_date', 'planned_start_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_agendas', function (Blueprint $table) {
            $table->dropForeign(['daily_plan_id']);
            $table->dropIndex(['daily_plan_id', 'sort_order']);
            $table->dropIndex(['agenda_date', 'planned_start_at']);
            $table->dropColumn([
                'daily_plan_id', 'duration_minutes', 'sort_order', 'planned_start_at',
                'planned_end_at', 'plan_detail', 'planned_quantity',
            ]);
        });
    }
};

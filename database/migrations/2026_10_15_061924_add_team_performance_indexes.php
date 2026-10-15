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
        Schema::table('leads', function (Blueprint $table): void {
            $table->index(['team_id', 'tanggal_lead', 'status_lead'], 'leads_team_date_status_index');
            $table->index(['sales_pic_id', 'tanggal_lead', 'status_lead'], 'leads_sales_date_status_index');
            $table->index(['team_id', 'next_follow_up_at', 'status_lead'], 'leads_team_followup_status_index');
        });

        Schema::table('sales_agendas', function (Blueprint $table): void {
            $table->index(['team_id', 'agenda_date', 'status'], 'agendas_team_date_status_index');
            $table->index(['owner_id', 'agenda_date', 'status'], 'agendas_owner_date_status_index');
        });

        Schema::table('sales_agenda_assignment_histories', function (Blueprint $table): void {
            $table->index(['to_user_id', 'action', 'created_at'], 'agenda_history_to_action_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('leads_team_date_status_index');
            $table->dropIndex('leads_sales_date_status_index');
            $table->dropIndex('leads_team_followup_status_index');
        });

        Schema::table('sales_agendas', function (Blueprint $table): void {
            $table->dropIndex('agendas_team_date_status_index');
            $table->dropIndex('agendas_owner_date_status_index');
        });

        Schema::table('sales_agenda_assignment_histories', function (Blueprint $table): void {
            $table->dropIndex('agenda_history_to_action_date_index');
        });
    }
};

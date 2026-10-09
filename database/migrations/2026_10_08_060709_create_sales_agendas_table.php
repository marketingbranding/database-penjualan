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
        Schema::create('sales_agendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('sales_case_id')->nullable()->constrained()->nullOnDelete();
            $table->date('agenda_date');
            $table->string('activity_category', 60);
            $table->string('title');
            $table->string('location')->nullable();
            $table->text('activity_result')->nullable();
            $table->string('status', 20)->default('planned');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'agenda_date']);
            $table->index(['created_by', 'agenda_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_agendas');
    }
};

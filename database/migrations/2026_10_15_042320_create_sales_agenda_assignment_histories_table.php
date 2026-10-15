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
        Schema::create('sales_agenda_assignment_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sales_agenda_id')->constrained()->cascadeOnDelete();
            $table->string('action', 40);
            $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['sales_agenda_id', 'created_at'], 'agenda_history_agenda_date_idx');
            $table->index(['from_user_id', 'action'], 'agenda_history_from_action_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_agenda_assignment_histories');
    }
};

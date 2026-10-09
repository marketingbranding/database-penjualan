<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_case_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->restrictOnDelete();
            $table->string('change_type', 30);
            $table->string('process_stage', 30);
            $table->string('restart_stage', 30)->nullable();
            $table->boolean('sp3k_issued')->default(false);
            $table->foreignId('old_consumer_id')->constrained('consumers')->restrictOnDelete();
            $table->foreignId('new_consumer_id')->nullable()->constrained('consumers')->restrictOnDelete();
            $table->foreignId('old_lot_id')->constrained('lots')->restrictOnDelete();
            $table->foreignId('new_lot_id')->nullable()->constrained('lots')->restrictOnDelete();
            $table->text('reason');
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('completed');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['sales_case_id', 'change_type']);
            $table->index(['process_stage', 'restart_stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_case_change_requests');
    }
};

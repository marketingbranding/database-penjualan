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
        Schema::create('consumer_migration_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_migration_application_id')
                ->unique()
                ->constrained('consumer_migration_applications')
                ->cascadeOnDelete();
            $table->string('source_system', 50);
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_transaction_id', 150)->nullable();
            $table->string('source_customer_ref', 150)->nullable();
            $table->string('source_current_kavling', 150)->nullable();
            $table->string('source_transaction_status', 100)->nullable();
            $table->string('source_bank_status', 100)->nullable();
            $table->string('source_kavling_status', 100)->nullable();
            $table->string('source_stage_status', 100)->nullable();
            $table->string('suggested_decision', 50)->nullable();
            $table->string('decision', 50)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained('consumers')->nullOnDelete();
            $table->foreignId('target_kavling_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->string('reconciliation_status', 32)->default('PENDING');
            $table->string('source_payload_hash', 64)->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_transaction_id']);
            $table->index(['reconciliation_status', 'branch_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumer_migration_reconciliations');
    }
};

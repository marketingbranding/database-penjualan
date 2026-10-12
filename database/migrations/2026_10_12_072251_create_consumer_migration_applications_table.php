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
        Schema::create('consumer_migration_applications', function (Blueprint $table) {
            $table->id();
            $table->string('source_system', 50);
            $table->string('source_application_id', 100);
            $table->string('source_transaction_id', 150)->nullable();
            $table->string('source_customer_ref', 150)->nullable();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_current_kavling', 150)->nullable();
            $table->string('source_kavling_id', 100)->nullable();
            $table->string('source_sales_user_id', 100)->nullable();
            $table->string('source_sales_lead_id', 100)->nullable();
            $table->string('application_status', 50)->nullable();
            $table->string('consumer_status', 50)->nullable();
            $table->string('current_stage', 50)->nullable();
            $table->string('source_last_process', 100)->nullable();
            $table->string('source_completeness_status', 80)->nullable();
            $table->boolean('status_cash')->nullable();
            $table->date('booking_date')->nullable();
            $table->date('akad_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('source_payload_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_application_id']);
            $table->index(['source_system', 'source_transaction_id']);
            $table->index(['branch_id', 'project_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumer_migration_applications');
    }
};

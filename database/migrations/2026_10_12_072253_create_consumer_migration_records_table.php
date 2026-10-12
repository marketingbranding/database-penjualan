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
        Schema::create('consumer_migration_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumer_migration_application_id')
                ->constrained('consumer_migration_applications')
                ->cascadeOnDelete();
            $table->string('source_system', 50);
            $table->string('source_transaction_id', 150)->nullable();
            $table->string('source_type', 80);
            $table->string('source_record_id', 200);
            $table->string('source_branch_code', 30)->nullable();
            $table->string('payload_hash', 64)->nullable();
            $table->json('payload');
            $table->string('target_type', 100)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->timestamps();

            $table->unique(['source_system', 'source_type', 'source_record_id']);
            $table->index(['consumer_migration_application_id', 'source_type']);
            $table->index(['source_system', 'source_transaction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumer_migration_records');
    }
};

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
        Schema::create('bank_processes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sales_case_filing_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('sp3k_number')->unique()->nullable();
            $table->unsignedInteger('attempt_number')->default(1);
            $table->date('submitted_at')->nullable();
            $table->date('sp3k_date')->nullable();
            $table->string('result')->nullable();
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->string('approved_tenor')->nullable();
            $table->string('status')->default('proses');
            $table->string('revision_category')->nullable();
            $table->text('revision_detail')->nullable();
            $table->text('notes')->nullable();
            $table->date('started_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['sales_case_id', 'attempt_number']);
            $table->index(['status', 'submitted_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_processes');
    }
};

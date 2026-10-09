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
        Schema::create('sales_case_filings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bi_checking_id')->nullable()->constrained('bi_checkings')->nullOnDelete();
            $table->foreignId('bank_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('filing_id')->unique();
            $table->date('started_at')->nullable();
            $table->date('received_by_bank_at')->nullable();
            $table->decimal('requested_amount', 15, 2)->nullable();
            $table->string('requested_tenor')->nullable();
            $table->string('status')->default('input');
            $table->date('completed_at')->nullable();
            $table->string('delay_category')->nullable();
            $table->string('waiting_for')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->date('deadline_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_case_filings');
    }
};

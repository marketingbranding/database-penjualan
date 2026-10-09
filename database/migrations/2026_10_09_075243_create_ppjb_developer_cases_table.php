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
        Schema::create('ppjb_developer_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_process_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_case_filing_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ppjb_number')->unique();
            $table->date('sp3k_date')->nullable();
            $table->date('started_at')->nullable();
            $table->date('signed_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('delay_category')->nullable();
            $table->string('waiting_for')->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->date('deadline_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ppjb_developer_cases');
    }
};

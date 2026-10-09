<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bi_checkings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_unit_id')->constrained()->restrictOnDelete();
            $table->date('checking_date');
            $table->string('slik_result', 30);
            $table->text('notes')->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->string('lead_time_status', 30)->nullable();
            $table->string('bank_decision', 20);
            $table->timestamps();

            $table->index(['sales_case_id', 'checking_date']);
            $table->index(['bank_id', 'bank_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bi_checkings');
    }
};

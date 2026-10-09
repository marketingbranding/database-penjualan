<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('psjb_utj_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->unique()->constrained()->restrictOnDelete();
            $table->string('psjb_id', 100)->unique();
            $table->date('psjb_date');
            $table->foreignId('coordinator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->date('utj_date')->nullable();
            $table->decimal('utj_amount', 15, 2)->default(0);
            $table->decimal('dp_all_in', 15, 2)->default(0);
            $table->decimal('dp_installment_amount', 15, 2)->default(0);
            $table->unsignedSmallInteger('dp_installment_count')->nullable();
            $table->decimal('land_area_m2', 10, 2)->default(0);
            $table->decimal('land_price_per_m2', 15, 2)->default(0);
            $table->decimal('land_total', 15, 2)->default(0);
            $table->text('payment_detail')->nullable();
            $table->string('external_transaction_id', 100)->nullable();
            $table->string('external_status', 100)->nullable();
            $table->string('external_method', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('consumer_status', 30)->default('LANJUT');
            $table->date('consumer_status_date')->nullable();
            $table->string('delay_category', 100)->nullable();
            $table->string('filing_status', 50)->nullable();
            $table->unsignedInteger('filing_lead_time')->nullable();
            $table->string('filing_lead_time_status', 50)->nullable();
            $table->string('ongoing_delay_category', 100)->nullable();
            $table->text('filing_delay_reason')->nullable();
            $table->timestamps();

            $table->index(['consumer_status', 'psjb_date']);
            $table->index(['filing_status', 'filing_lead_time_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psjb_utj_cases');
    }
};

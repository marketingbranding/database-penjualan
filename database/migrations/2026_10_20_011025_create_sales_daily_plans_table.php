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
        Schema::create('sales_daily_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->date('plan_date');
            $table->string('status', 20)->default('draft');
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('last_modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_id', 'plan_date'], 'sales_daily_plans_sales_date_unique');
            $table->index(['team_id', 'plan_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_daily_plans');
    }
};

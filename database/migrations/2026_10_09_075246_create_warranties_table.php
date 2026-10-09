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
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bast_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consumer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('warranty_number')->unique();
            $table->string('status')->default('belum_dipilih');
            $table->string('complaint_status')->nullable();
            $table->date('reported_at')->nullable();
            $table->date('sales_to_sam_at')->nullable();
            $table->date('sam_to_sat_at')->nullable();
            $table->date('sat_to_sam_at')->nullable();
            $table->date('sam_to_sales_at')->nullable();
            $table->date('sales_to_consumer_at')->nullable();
            $table->text('detail')->nullable();
            $table->date('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'reported_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warranties');
    }
};

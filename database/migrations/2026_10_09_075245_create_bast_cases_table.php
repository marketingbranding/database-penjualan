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
        Schema::create('bast_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('akad_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bast_number')->unique();
            $table->date('akad_date')->nullable();
            $table->decimal('building_progress', 5, 2)->nullable();
            $table->date('ready_100_at')->nullable();
            $table->date('started_at')->nullable();
            $table->date('bast_date')->nullable();
            $table->string('delay_category')->nullable();
            $table->string('status')->default('pending');
            $table->date('deadline_at')->nullable();
            $table->date('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bast_cases');
    }
};

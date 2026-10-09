<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_case_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->text('reason');
            $table->string('process_stage', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sales_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_case_status_histories');
    }
};

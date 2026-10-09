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
        Schema::create('akad_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ppjb_developer_case_id')->nullable()->constrained()->nullOnDelete();
            $table->string('akad_number')->unique();
            $table->date('ppjb_signed_at')->nullable();
            $table->date('started_at')->nullable();
            $table->date('akad_date')->nullable();
            $table->string('quality')->nullable();
            $table->string('down_payment_status')->nullable();
            $table->string('consumer_issue')->nullable();
            $table->string('bank_issue')->nullable();
            $table->text('issue_detail')->nullable();
            $table->decimal('building_progress', 5, 2)->nullable();
            $table->string('utility_status')->nullable();
            $table->string('status')->default('pending');
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
        Schema::dropIfExists('akad_cases');
    }
};

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
        Schema::create('lead_cutover_dispositions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('classification', 80);
            $table->string('evidence_confidence', 40)->nullable();
            $table->dateTime('cutover_at');
            $table->dateTime('current_month_boundary');
            $table->unsignedBigInteger('old_sales_pic_id')->nullable();
            $table->unsignedBigInteger('new_sales_pic_id')->nullable();
            $table->unsignedBigInteger('old_team_id')->nullable();
            $table->unsignedBigInteger('new_team_id')->nullable();
            $table->json('evidence')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['classification', 'cutover_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_cutover_dispositions');
    }
};

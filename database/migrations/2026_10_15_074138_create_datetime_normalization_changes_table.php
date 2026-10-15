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
        Schema::create('datetime_normalization_changes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('batch_id')->index();
            $table->string('table_name', 100);
            $table->string('column_name', 100);
            $table->unsignedBigInteger('row_id');
            $table->string('classification', 20);
            $table->string('old_value')->nullable();
            $table->string('new_value')->nullable();
            $table->string('old_source', 20)->nullable();
            $table->string('new_source', 20)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['table_name', 'column_name', 'row_id'], 'datetime_normalization_row_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('datetime_normalization_changes');
    }
};

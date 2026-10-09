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
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_block_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('code', 20);
            $table->string('status', 20)->default('available');
            $table->timestamps();

            $table->unique(['project_id', 'code']);
            $table->unique(['project_block_id', 'number']);
            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};

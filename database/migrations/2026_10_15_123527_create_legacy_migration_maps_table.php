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
        Schema::create('legacy_migration_maps', function (Blueprint $table): void {
            $table->id();
            $table->string('source_connection', 80);
            $table->string('entity', 80);
            $table->unsignedBigInteger('source_id');
            $table->unsignedBigInteger('target_id');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['source_connection', 'entity', 'source_id'], 'legacy_mig_maps_source_unique');
            $table->index(['entity', 'target_id'], 'legacy_mig_maps_target_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legacy_migration_maps');
    }
};

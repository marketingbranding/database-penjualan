<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_time_targets', function (Blueprint $table) {
            $table->id();
            $table->string('process', 30)->unique();
            $table->string('target_description', 150);
            $table->unsignedInteger('target_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_time_targets');
    }
};

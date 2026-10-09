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
        Schema::table('branches', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('code', 30)->nullable()->change();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
        });

        Schema::table('project_blocks', function (Blueprint $table) {
            $table->string('code', 5)->nullable()->change();
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->string('promo_id', 50)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('code', 30)->nullable(false)->change();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('code', 30)->nullable(false)->change();
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('code')->nullable(false)->change();
        });

        Schema::table('project_blocks', function (Blueprint $table) {
            $table->string('code', 5)->nullable(false)->change();
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->string('promo_id', 50)->nullable(false)->change();
        });
    }
};

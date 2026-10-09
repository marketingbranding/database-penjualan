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
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->after('parent_id')->constrained('branches')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->after('branch_id')->constrained('users')->nullOnDelete();
            $table->foreignId('coordinator_id')->nullable()->after('supervisor_id')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropConstrainedForeignId('coordinator_id');
        });
    }
};

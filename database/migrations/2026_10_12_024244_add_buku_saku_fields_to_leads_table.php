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
        Schema::table('leads', function (Blueprint $table) {
            $table->dateTime('next_follow_up_at')->nullable()->after('keterangan');
            $table->text('next_follow_up_note')->nullable()->after('next_follow_up_at');
            $table->string('qualification_status', 40)->default('not_qualified')->after('next_follow_up_note');
            $table->text('qualification_note')->nullable()->after('qualification_status');
            $table->dateTime('qualified_at')->nullable()->after('qualification_note');
            $table->foreignId('qualified_by')->nullable()->after('qualified_at')->constrained('users')->nullOnDelete();
            $table->index(['branch_id', 'next_follow_up_at']);
            $table->index(['qualification_status', 'next_follow_up_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['qualified_by']);
            $table->dropIndex(['branch_id', 'next_follow_up_at']);
            $table->dropIndex(['qualification_status', 'next_follow_up_at']);
            $table->dropColumn([
                'next_follow_up_at', 'next_follow_up_note', 'qualification_status',
                'qualification_note', 'qualified_at', 'qualified_by',
            ]);
        });
    }
};

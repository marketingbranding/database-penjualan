<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_cases', function (Blueprint $table) {
            $table->string('consumer_status', 30)->default('LANJUT')->after('promotion_id');
            $table->date('consumer_status_date')->nullable()->after('consumer_status');
            $table->text('consumer_status_reason')->nullable()->after('consumer_status_date');
            $table->foreignId('consumer_status_changed_by')->nullable()->after('consumer_status_reason')->constrained('users')->nullOnDelete();
            $table->index(['consumer_status', 'consumer_status_date']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_cases', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumer_status_changed_by');
            $table->dropColumn(['consumer_status', 'consumer_status_date', 'consumer_status_reason']);
        });
    }
};

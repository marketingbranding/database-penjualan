<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('sales_pic_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('leads')->whereNull('sales_pic_id')->exists()) {
            throw new RuntimeException('Cannot make leads.sales_pic_id required while unresolved Leads remain.');
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('sales_pic_id')->nullable(false)->change();
        });
    }
};

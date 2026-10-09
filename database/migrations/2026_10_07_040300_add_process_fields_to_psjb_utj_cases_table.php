<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('psjb_utj_cases', function (Blueprint $table) {
            $table->string('process_stage', 30)->default('psjb_utj')->after('psjb_date');
            $table->boolean('sp3k_issued')->default(false)->after('process_stage');
        });
    }

    public function down(): void
    {
        Schema::table('psjb_utj_cases', function (Blueprint $table) {
            $table->dropColumn(['process_stage', 'sp3k_issued']);
        });
    }
};

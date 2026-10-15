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
        Schema::table('sales_agendas', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable()->after('created_by')->constrained('users')->restrictOnDelete();
        });

        DB::table('sales_agendas')->update(['owner_id' => DB::raw('created_by')]);

        Schema::table('sales_agendas', function (Blueprint $table): void {
            $table->unsignedBigInteger('owner_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_agendas', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};

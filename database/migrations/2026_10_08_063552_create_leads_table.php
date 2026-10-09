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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('id_lead', 40)->unique();
            $table->string('id_promo', 100)->nullable();
            $table->string('nama_promo')->nullable();
            $table->date('tanggal_lead');
            $table->string('sumber_lead', 40);
            $table->string('kanal_masuk', 60);
            $table->string('aktivitas_lead', 60);
            $table->string('nama_konsumen');
            $table->string('no_hp', 40);
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sales_pic_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status_lead', 60)->default('No Respon');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'tanggal_lead']);
            $table->index(['team_id', 'tanggal_lead']);
            $table->index(['sales_pic_id', 'tanggal_lead']);
            $table->index('status_lead');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};

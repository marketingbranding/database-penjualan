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
        Schema::create('consumers', function (Blueprint $table) {
            $table->id();
            $table->string('no_ktp', 16)->unique();
            $table->string('nama_konsumen');
            $table->date('tanggal_lahir');
            $table->string('pekerjaan');
            $table->string('detail_pekerjaan')->nullable();
            $table->text('alamat');
            $table->string('kelurahan');
            $table->string('kecamatan');
            $table->string('kabupaten_kota');
            $table->string('no_hp', 30);
            $table->string('nama_kontak_darurat');
            $table->string('no_hp_kontak_darurat', 30);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consumers');
    }
};

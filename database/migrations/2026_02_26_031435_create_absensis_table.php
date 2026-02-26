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
        Schema::create('absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('lokasi_id')->constrained()->onDelete('cascade');
            $table->string('titik_koordinat_lokasi');
            $table->string('titik_koordinat_kamu')->nullable();
            $table->string('foto_wajah')->nullable();
            $table->enum('tipe_absen', ['masuk', 'pulang'])->default('masuk');
            $table->timestamp('waktu_absen');
            $table->timestamps();

            //index untuk performa query
            $table->index(['user_id', 'waktu_absen']);
            $table->index(['user_id', 'tipe_absen', 'waktu_absen']);

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensis');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mabars', function (Blueprint $table) {
            $table->id();

            // Pembuat / Host mabar (wajib login)
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Informasi kegiatan
            $table->string('title');                     // Judul sesi, cth: "Sparring Futsal Seru!"
            $table->string('sport_type');                // futsal / badminton / dll
            $table->string('venue_name');                // Nama dan lokasi venue
            $table->date('date');                        // Tanggal kegiatan
            $table->string('time');                      // Rentang waktu, cth: "19:00 - 21:00"

            // Kapasitas & biaya
            $table->unsignedTinyInteger('max_participants'); // Jumlah total peserta yang dibutuhkan
            $table->unsignedInteger('price_per_person');     // Biaya per orang (dalam Rupiah)

            // Status sesi mabar
            // open: masih bisa didaftarkan; full: sudah penuh; cancelled: dibatalkan host; done: selesai
            $table->enum('status', ['open', 'full', 'cancelled', 'done'])->default('open');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mabars');
    }
};

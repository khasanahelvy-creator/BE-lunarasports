<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menambahkan kolom jam operasional (open_time & close_time) ke tabel venues.
     * Kolom ini digunakan oleh frontend untuk generate slot waktu booking secara dinamis.
     * Default: 08:00 s/d 23:00 (mengikuti default lama yang statis).
     */
    public function up(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'open_time')) {
                $table->time('open_time')->default('08:00:00')->after('status')
                      ->comment('Jam buka operasional venue (generate slot booking)');
            }
            if (!Schema::hasColumn('venues', 'close_time')) {
                $table->time('close_time')->default('23:00:00')->after('open_time')
                      ->comment('Jam tutup operasional venue (slot terakhir yang tersedia)');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropColumn(['open_time', 'close_time']);
        });
    }
};

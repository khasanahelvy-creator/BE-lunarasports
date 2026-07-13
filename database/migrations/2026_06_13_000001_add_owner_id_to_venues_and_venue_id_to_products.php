<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan owner_id ke tabel venues
        //    Ini menghubungkan setiap venue ke user yang mendaftarkan/mengelolanya
        Schema::table('venues', function (Blueprint $table) {
            if (!Schema::hasColumn('venues', 'owner_id')) {
                $table->foreignId('owner_id')
                      ->nullable()
                      ->constrained('users')
                      ->onDelete('set null')
                      ->after('id');
            }
            // Tambahkan juga kolom status pendaftaran venue
            if (!Schema::hasColumn('venues', 'status')) {
                $table->string('status')->default('approved')->after('peraturan');
                // Status: 'pending' (menunggu persetujuan), 'approved' (aktif), 'rejected'
            }
        });

        // 2. Tambahkan venue_id ke tabel products
        //    Ini memastikan produk di toko hanya muncul di venue pemiliknya
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'venue_id')) {
                $table->foreignId('venue_id')
                      ->nullable()
                      ->constrained('venues')
                      ->onDelete('set null')
                      ->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['owner_id', 'status']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
    }
};

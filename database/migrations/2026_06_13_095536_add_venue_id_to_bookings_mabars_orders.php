<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah venue_id ke bookings
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'venue_id')) {
                $table->unsignedBigInteger('venue_id')->nullable()->after('court_id');
                $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
            }
        });

        // Tambah venue_id ke mabars
        Schema::table('mabars', function (Blueprint $table) {
            if (!Schema::hasColumn('mabars', 'venue_id')) {
                $table->unsignedBigInteger('venue_id')->nullable()->after('user_id');
                $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
            }
        });

        // Tambah venue_id ke orders (kantin)
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'venue_id')) {
                $table->unsignedBigInteger('venue_id')->nullable()->after('id');
                $table->foreign('venue_id')->references('id')->on('venues')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
        Schema::table('mabars', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['venue_id']);
            $table->dropColumn('venue_id');
        });
    }
};

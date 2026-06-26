<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambahkan kolom snap_order_id ke tabel bookings.
     * Kolom ini menyimpan order_id yang dikirim ke Midtrans saat membuat Snap Token,
     * sehingga dapat digunakan kembali untuk query status transaksi secara manual.
     * Format: 'LNR-{uniqid()}-{booking_id}'
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'snap_order_id')) {
                $table->string('snap_order_id')->nullable()->after('payment_method')
                      ->comment('Order ID yang dikirim ke Midtrans, untuk keperluan sync status manual');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('snap_order_id');
        });
    }
};

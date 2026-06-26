<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambahkan is_rental ke tabel products
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'is_rental')) {
                $table->boolean('is_rental')->default(false)->after('image');
                // is_rental = true: barang bisa disewa (raket, dll)
                // is_rental = false: barang dibeli (minuman, snack, dll)
            }
        });

        // 2. Tambahkan kolom addons ke tabel bookings
        Schema::table('bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bookings', 'addons')) {
                $table->json('addons')->nullable()->after('payment_method');
                // Format: [{"product_id":1,"name":"Pocari","qty":2,"price":8000}]
            }
            if (!Schema::hasColumn('bookings', 'addons_total')) {
                $table->integer('addons_total')->default(0)->after('addons');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('is_rental');
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['addons', 'addons_total']);
        });
    }
};

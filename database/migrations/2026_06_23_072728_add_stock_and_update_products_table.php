<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom 'stock' dan 'category' (jika belum ada) ke tabel products.
     * Hapus kolom lama yang tidak relevan: brand, rating, sold.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Tambah kolom baru
            if (!Schema::hasColumn('products', 'stock')) {
                $table->integer('stock')->default(0)->after('price');
            }
            if (!Schema::hasColumn('products', 'category')) {
                $table->string('category')->default('Minuman')->after('name');
            }
            // Hapus kolom lama yang tidak dipakai di fitur Market ini
            if (Schema::hasColumn('products', 'brand')) {
                $table->dropColumn('brand');
            }
            if (Schema::hasColumn('products', 'rating')) {
                $table->dropColumn('rating');
            }
            if (Schema::hasColumn('products', 'sold')) {
                $table->dropColumn('sold');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['stock', 'category']);
            $table->string('brand')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->integer('sold')->default(0);
        });
    }
};
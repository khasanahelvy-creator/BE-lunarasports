<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('kategori'); // Badminton, Futsal, Mini Soccer, dll
            $table->string('lokasi');
            $table->decimal('jarak', 8, 2)->nullable();
            $table->integer('harga_mulai')->default(0);
            $table->decimal('rating', 3, 1)->default(0);
            
            // Simpan array fasilitas & peraturan dalam bentuk JSON
            $table->json('fasilitas_utama')->nullable(); 
            $table->json('fasilitas_lengkap')->nullable();
            $table->json('peraturan')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venues');
    }
};
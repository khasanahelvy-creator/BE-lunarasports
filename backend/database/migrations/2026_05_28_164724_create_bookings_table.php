<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            
            // Relasi ke User (bisa null jika belum ada sistem login wajib)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            
            // Relasi ke Court (Sub-lapangan)
            $table->foreignId('court_id')->constrained()->onDelete('cascade');
            
            // Data Pemesan Tambahan (Opsional untuk Guest)
            $table->string('customer_name');
            $table->string('customer_email');
            
            // Waktu Bermain
            $table->date('booking_date');
            $table->json('time_slots'); // Menyimpan array jam, misal: ["19:00", "20:00"]
            
            // Detail Pembayaran
            $table->integer('subtotal');
            $table->integer('admin_fee')->default(2500);
            $table->integer('total_price');
            $table->string('payment_method'); // qris, va, ewallet
            $table->string('status')->default('pending'); // pending, paid, cancelled
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
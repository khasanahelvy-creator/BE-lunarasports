<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
// Tambahkan import konfigurasi Midtrans
use Midtrans\Config;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Pengaturan Midtrans
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        // Set ke Development/Sandbox Environment (default false)
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        // Set sanitization (bersihkan input data)
        Config::$isSanitized = true;
        // Set 3DS transaksi untuk credit card
        Config::$is3ds = true;
    }
}
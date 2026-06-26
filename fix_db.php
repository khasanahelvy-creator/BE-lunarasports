<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Venue;
use App\Models\Product;

// 1. Dapatkan admin utama
$admin = User::where('email', 'admin@lunara.com')->first();
if (!$admin) {
    echo "Admin tidak ditemukan.\n";
    exit;
}

// 2. Dapatkan venue pertama atau buat baru
$venue = Venue::first();
if (!$venue) {
    $venue = Venue::create([
        'owner_id' => $admin->id,
        'name' => 'Lunara Sports',
        'kategori' => 'Futsal',
        'lokasi' => 'Makassar',
        'status' => 'approved'
    ]);
    echo "Venue baru dibuat.\n";
} else {
    $venue->owner_id = $admin->id;
    $venue->save();
    echo "Venue diupdate kepemilikannya ke admin@lunara.com.\n";
}

// 3. Update semua produk untuk mengarah ke venue ini
Product::whereNull('venue_id')->update(['venue_id' => $venue->id]);
echo "Produk diupdate ke venue_id: " . $venue->id . "\n";

echo "Selesai.\n";
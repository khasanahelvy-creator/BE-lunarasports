<?php

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Hash;

// Seed GOR 1: Gelora Futsal Arena
$admin1 = User::updateOrCreate(['email' => 'admin.futsal@lunara.com'], [
    'name' => 'Admin Gelora Futsal',
    'password' => Hash::make('password123'),
    'role' => 'admin',
    'is_admin' => true,
]);

$venue1 = Venue::updateOrCreate(['name' => 'Gelora Futsal Arena'], [
    'owner_id' => $admin1->id,
    'kategori' => 'Futsal',
    'lokasi' => 'Samata, Gowa',
    'phone' => '08123456781',
    'jarak' => 5.0,
    'harga_mulai' => 150000,
    'rating' => '4.8',
    'fasilitas_utama' => ['Rumput Sintetis', 'Parkir Luas', 'Kantin'],
    'fasilitas_lengkap' => ['Rumput Sintetis FIFA', 'Parkir Motor & Mobil', 'Kantin', 'Toilet', 'Mushola'],
    'peraturan' => ['Wajib sepatu futsal', 'Hadir 10 menit sebelum jadwal'],
    'status' => 'active',
]);

echo 'Venue 1: ' . $venue1->id . PHP_EOL;

// Seed GOR 2: Minisoccer UINAM
$admin2 = User::updateOrCreate(['email' => 'admin.soccer@lunara.com'], [
    'name' => 'Admin Minisoccer UINAM',
    'password' => Hash::make('password123'),
    'role' => 'admin',
    'is_admin' => true,
]);

$venue2 = Venue::updateOrCreate(['name' => 'Minisoccer UINAM'], [
    'owner_id' => $admin2->id,
    'kategori' => 'Mini Soccer',
    'lokasi' => 'Samata, Gowa',
    'phone' => '08123456782',
    'jarak' => 3.5,
    'harga_mulai' => 250000,
    'rating' => '4.7',
    'fasilitas_utama' => ['Standar FIFA', 'Lampu Sorot', 'Tribun'],
    'fasilitas_lengkap' => ['Rumput Sintetis', 'Lampu Sorot LED', 'Tribun Penonton', 'Ruang Ganti', 'Kantin'],
    'peraturan' => ['Wajib sepatu pul', 'Dilarang keras kekerasan'],
    'status' => 'active',
]);

echo 'Venue 2: ' . $venue2->id . PHP_EOL;
echo 'Seeding selesai!';

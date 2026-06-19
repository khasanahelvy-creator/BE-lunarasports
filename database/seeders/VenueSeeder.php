<?php

namespace Database\Seeders;

use App\Models\Venue;
use App\Models\Court;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Buat Data Venue Pertama (GOR Cempaka Putih)
        $venue1 = Venue::create([
            'name' => 'GOR Cempaka Putih',
            'kategori' => 'Badminton',
            'lokasi' => 'Panakkukang',
            'jarak' => 2.5,
            'harga_mulai' => 80000,
            'rating' => 4.9,
            'fasilitas_utama' => ['Karpet BWF', 'Parkir Luas', 'Kantin'],
            'fasilitas_lengkap' => ['Karpet Standar BWF', 'Parkir Mobil & Motor', 'Kantin', 'Toilet', 'Loker'],
            'peraturan' => ['Wajib sepatu non-marking', 'Dilarang bawa makanan dari luar', 'Hadir 10 menit sebelum jadwal'],
        ]);

        // Buat Sub-Lapangan (Courts) untuk Venue 1
        Court::create(['venue_id' => $venue1->id, 'name' => 'Lapangan 1 (VIP)', 'harga' => 100000, 'image' => 'https://placehold.co/600x400/2FA084/FFFFFF?text=Badminton+VIP']);
        Court::create(['venue_id' => $venue1->id, 'name' => 'Lapangan 2 (Reguler)', 'harga' => 80000, 'image' => 'https://placehold.co/600x400/1F6F5F/FFFFFF?text=Badminton+Reguler+2']);

        // 2. Buat Data Venue Kedua (Sudiang Mini Soccer)
        $venue2 = Venue::create([
            'name' => 'Sudiang Mini Soccer',
            'kategori' => 'Mini Soccer',
            'lokasi' => 'Biringkanaya',
            'jarak' => 12.0,
            'harga_mulai' => 250000,
            'rating' => 5.0,
            'fasilitas_utama' => ['Standar FIFA', 'Lampu Sorot LED', 'Cafe'],
            'fasilitas_lengkap' => ['Rumput Sintetis FIFA', 'Lampu Sorot', 'Cafe / Sports Bar', 'Tribun', 'Ruang Ganti'],
            'peraturan' => ['Wajib sepatu pul', 'Wajib deposit Rp 50.000', 'Ikuti peluit wasit/operator'],
        ]);

        // Buat Sub-Lapangan (Courts) untuk Venue 2
        Court::create(['venue_id' => $venue2->id, 'name' => 'Main Field (11v11)', 'harga' => 500000, 'image' => 'https://placehold.co/600x400/2FA084/FFFFFF?text=Mini+Soccer+Besar']);
        Court::create(['venue_id' => $venue2->id, 'name' => 'Half Field (7v7)', 'harga' => 250000, 'image' => 'https://placehold.co/600x400/1F6F5F/FFFFFF?text=Mini+Soccer+Kecil']);
    }
}
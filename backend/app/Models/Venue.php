<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'owner_id', 'name', 'kategori', 'lokasi', 'jarak', 'harga_mulai', 'rating',
        'fasilitas_utama', 'fasilitas_lengkap', 'peraturan', 'status',
        'open_time', 'close_time',  // Jam operasional untuk generate slot booking
    ];

    // Beritahu Laravel bahwa kolom ini berisi array JSON
    protected $casts = [
        'fasilitas_utama'   => 'array',
        'fasilitas_lengkap' => 'array',
        'peraturan'         => 'array',
    ];

    // Atribut default — jika kolom belum diisi di DB, gunakan nilai ini
    protected $attributes = [
        'open_time'  => '08:00:00',
        'close_time' => '23:00:00',
    ];

    // Relasi ke User yang memiliki venue ini (Admin Venue)
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    // Relasi ke Lapangan milik venue ini
    public function courts()
    {
        return $this->hasMany(Court::class);
    }

    // Relasi ke Produk/Barang yang dijual di venue ini
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
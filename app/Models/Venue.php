<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'kategori', 'lokasi', 'jarak', 'harga_mulai', 'rating', 
        'fasilitas_utama', 'fasilitas_lengkap', 'peraturan'
    ];

    // Beritahu Laravel bahwa kolom ini berisi array JSON
    protected $casts = [
        'fasilitas_utama' => 'array',
        'fasilitas_lengkap' => 'array',
        'peraturan' => 'array',
    ];

    public function courts()
    {
        return $this->hasMany(Court::class);
    }
}
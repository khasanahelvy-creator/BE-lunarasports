<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
feat/api-setup-and-config
        'name', 
        'category', 
        'brand', 
        'price', 
        'rating', 
        'sold', 
        'image'
    ];
        'venue_id',
        'name',
        'category',
        'price',
        'stock',
        'image',
        'is_rental',
    ];

    protected $casts = [
        'is_rental' => 'boolean',
    ];

    // Relasi ke Venue pemilik produk ini
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
 main
}
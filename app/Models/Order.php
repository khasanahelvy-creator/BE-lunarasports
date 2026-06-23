<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id', 'customer_name', 'customer_email', 'customer_phone', 'total_price', 'payment_method', 'status'
    ];

    // Status siklus GOR: unpaid -> ready_to_pickup -> completed

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    // Label status dalam Bahasa Indonesia untuk UI
    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'unpaid'           => 'Menunggu Pembayaran',
            'ready_to_pickup'  => 'Barang Siap Diambil di Kasir',
            'completed'        => 'Selesai',
            default            => $this->status,
        };
    }
}
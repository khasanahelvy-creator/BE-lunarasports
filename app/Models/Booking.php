<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'venue_id', 'court_id', 'customer_name', 'customer_email', 'customer_phone',
        'booking_date', 'time_slots', 'subtotal', 'admin_fee',
        'voucher_code', 'discount',
        'total_price', 'payment_method', 'status', 'addons', 'addons_total',
        'snap_order_id',  // Disimpan agar sync status ke Midtrans bisa dilakukan
    ];

    protected $casts = [
        'time_slots' => 'array',
        'addons'     => 'array',
    ];

    public function court()
    {
        return $this->belongsTo(Court::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
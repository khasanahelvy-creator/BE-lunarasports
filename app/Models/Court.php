<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Court extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id', 'name', 'harga', 'image'
    ];

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }
}
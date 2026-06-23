<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mabar extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'venue_id',
        'title',
        'sport_type',
        'venue_name',
        'date',
        'time',
        'max_participants',
        'price_per_person',
        'status',
    ];

    protected $casts = [
        'date'             => 'date',
        'price_per_person' => 'integer',
        'max_participants' => 'integer',
    ];

    /**
     * Relasi ke user yang membuat (host) mabar.
     */
    public function host()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * Relasi ke daftar peserta yang mendaftar mabar.
     */
    public function participants()
    {
        return $this->belongsToMany(User::class, 'mabar_participants')
                    ->withPivot('status')
                    ->withTimestamps();
    }
}

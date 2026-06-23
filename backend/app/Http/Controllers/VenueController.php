<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;

class VenueController extends Controller
{
    public function index()
    {
        // Mengambil semua data venue beserta relasi courts-nya
        $venues = Venue::with('courts')->get();
        
        // Mengembalikan data dalam format JSON
        return response()->json([
            'success' => true,
            'message' => 'Daftar Lapangan Lunara Sports',
            'data'    => $venues
        ], 200);
    }
}
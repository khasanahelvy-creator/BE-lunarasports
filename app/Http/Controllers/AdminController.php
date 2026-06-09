<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    // 1. Tarik semua data untuk tabel dashboard
    public function getAllBookings()
    {
        $bookings = Booking::with('court')->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $bookings]);
    }

    // 2. Fungsi untuk Scanner (Mengecek apakah tiket valid)
    public function verifyTicket(Request $request)
    {
        // QR Code dari Frontend bentuknya: "LNR-15-LUNARASPORTS"
        $qrData = $request->qr_code;
        
        // Buang teks tambahannya untuk mengambil ID asli
        $code = str_replace('-LUNARASPORTS', '', $qrData); // Sisa: LNR-15
        $parts = explode('-', $code);
        $bookingId = end($parts); // Sisa: 15

        $booking = Booking::with('court')->find($bookingId);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Tiket tidak ditemukan!'], 404);
        }

        if ($booking->status !== 'paid') {
            return response()->json(['success' => false, 'message' => "Tiket bermasalah! Status tiket ini: {$booking->status}"], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Tiket Valid!',
            'data' => [
                'id'    => 'LNR-' . $booking->id,
                'name  ' => $booking->customer_name,
                'venue' => $booking->court->name ?? 'Lapangan',
                'time'  => is_array($booking->time_slots) ? implode(', ', $booking->time_slots) : $booking->time_slots,
            ]
        ]);
    }

    // 3. Fungsi untuk tombol "Izinkan Masuk" (Ubah status jadi 'used' / selesai)
    public function checkInTicket(Request $request)
    {
        $bookingId = str_replace('LNR-', '', $request->booking_id);
        $booking = Booking::find($bookingId);

        if($booking) {
            $booking->update(['status' => 'completed']); // Atau 'used'
            return response()->json(['success' => true, 'message' => 'Pelanggan berhasil Check-In!']);
        }

        return response()->json(['success' => false, 'message' => 'Gagal check-in'], 400);
    }
    // Fungsi Rekap Pendapatan 7 Hari Terakhir
    public function getWeeklyRevenue()
    {
        // Ambil data 7 hari ke belakang khusus yang sudah bayar (paid / completed)
        $revenue = Booking::whereIn('status', ['paid', 'completed'])
            ->where('created_at', '>=', \Carbon\Carbon::now()->subDays(7))
            ->select(
                \Illuminate\Support\Facades\DB::raw('DATE(created_at) as date'),
                \Illuminate\Support\Facades\DB::raw('SUM(total_price) as total_revenue')
            )
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $revenue
        ]);
    }
    // 4. Menarik Daftar Lapangan
    public function getCourts()
    {
        // Asumsi nama modelnya Court
        $courts = \App\Models\Court::all(); 
        return response()->json(['success' => true, 'data' => $courts]);
    }

    // 5. Menambah Lapangan Baru
    public function addCourt(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'harga' => 'required|numeric'
        ]);

        $court = \App\Models\Court::create([
            'name' => $request->name,
            'type' => $request->type, // misal: futsal / badminton
            'harga' => $request->harga,
            'image' => $request->image ?? 'https://placehold.co/600x400/EEEEEE/AAAAAA?text=Venue'
        ]);

        return response()->json(['success' => true, 'message' => 'Lapangan baru berhasil ditambahkan!']);
    }
}
<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Product;
use App\Models\Venue;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Ambil venue milik admin yang sedang login.
     * Jika admin belum punya venue (mis: admin pusat), return null.
     */
    private function getAdminVenue(Request $request): ?Venue
    {
        return Venue::where('owner_id', $request->user()->id)->first();
    }

    // 1. Tarik semua data untuk tabel dashboard
    public function getAllBookings(Request $request)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => true, 'data' => []]);
        }

        // Hanya tarik booking untuk lapangan yang ada di venue admin ini
        $bookings = Booking::whereHas('court', function($q) use ($venue) {
            $q->where('venue_id', $venue->id);
        })->with('court')->orderBy('created_at', 'desc')->get();

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
                'name'   => $booking->customer_name,
                'venue' => $booking->court->name ?? 'Lapangan',
                'time'  => is_array($booking->time_slots) ? implode(', ', $booking->time_slots) : $booking->time_slots,
            ]
        ]);
    }

    // 3. Fungsi untuk tombol "Izinkan Masuk" (Ubah status jadi 'completed')
    public function checkInTicket(Request $request)
    {
        $bookingId = str_replace('LNR-', '', $request->booking_id);
        $booking = Booking::find($bookingId);

        if($booking) {
            $booking->update(['status' => 'completed']);
            return response()->json(['success' => true, 'message' => 'Pelanggan berhasil Check-In!']);
        }

        return response()->json(['success' => false, 'message' => 'Gagal check-in'], 400);
    }

    // 4. Rekap Pendapatan 7 Hari Terakhir
    public function getWeeklyRevenue(Request $request)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $revenue = Booking::whereHas('court', function($q) use ($venue) {
                $q->where('venue_id', $venue->id);
            })
            ->whereIn('status', ['paid', 'completed'])
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

    // 5. Menarik Daftar Lapangan
    public function getCourts(Request $request)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $courts = \App\Models\Court::where('venue_id', $venue->id)->get(); 
        return response()->json(['success' => true, 'data' => $courts]);
    }

    // 6. Menambah Lapangan Baru
    public function addCourt(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'harga' => 'required|numeric'
        ]);

        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 403);
        }

        $court = \App\Models\Court::create([
            'venue_id' => $venue->id,
            'name' => $request->name,
            'type' => $request->type,
            'harga' => $request->harga,
            'image' => $request->image ?? 'https://placehold.co/600x400/EEEEEE/AAAAAA?text=Venue'
        ]);

        return response()->json(['success' => true, 'message' => 'Lapangan baru berhasil ditambahkan!']);
    }

    // 6b. Update Lapangan
    public function updateCourt(Request $request, $id)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 403);
        }

        $court = \App\Models\Court::where('id', $id)->where('venue_id', $venue->id)->first();
        if (!$court) {
            return response()->json(['success' => false, 'message' => 'Lapangan tidak ditemukan atau bukan milik Anda.'], 404);
        }

        $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'harga' => 'required|numeric'
        ]);

        $court->update([
            'name' => $request->name,
            'type' => $request->type,
            'harga' => $request->harga,
            'image' => $request->image ?? $court->image
        ]);

        return response()->json(['success' => true, 'message' => 'Lapangan berhasil diupdate!', 'data' => $court]);
    }

    // 6c. Hapus Lapangan
    public function deleteCourt(Request $request, $id)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 403);
        }

        $court = \App\Models\Court::where('id', $id)->where('venue_id', $venue->id)->first();
        if (!$court) {
            return response()->json(['success' => false, 'message' => 'Lapangan tidak ditemukan atau bukan milik Anda.'], 404);
        }

        $court->delete();
        return response()->json(['success' => true, 'message' => 'Lapangan berhasil dihapus.']);
    }

    // 7. Menarik Daftar Produk (Difilter per venue admin)
    public function getProducts(Request $request)
    {
        $venue = $this->getAdminVenue($request);

        if (!$venue) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = Product::orderBy('created_at', 'desc')->where('venue_id', $venue->id);
        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    // 8. Menambah Produk Baru (Otomatis dikaitkan ke venue admin)
    public function storeProduct(Request $request)
    {
        $request->validate([
            'name'      => 'required|string|max:255',
            'category'  => 'required|string',
            'price'     => 'required|numeric|min:0',
            'stock'     => 'required|integer|min:0',
            'image'     => 'nullable|url',
            'is_rental' => 'boolean',
        ]);

        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 403);
        }

        $product = Product::create([
            'venue_id'  => $venue->id,
            'name'      => $request->name,
            'category'  => $request->category,
            'price'     => $request->price,
            'stock'     => $request->stock,
            'image'     => $request->image ?? null,
            'is_rental' => $request->boolean('is_rental', false),
        ]);

        return response()->json(['success' => true, 'message' => 'Produk berhasil ditambahkan!', 'data' => $product]);
    }

    // 8b. Update Produk
    public function updateProduct(Request $request, $id)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 403);
        }

        $product = Product::where('id', $id)->where('venue_id', $venue->id)->first();
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan atau bukan milik Anda.'], 404);
        }

        $request->validate([
            'name'      => 'required|string|max:255',
            'category'  => 'required|string',
            'price'     => 'required|numeric|min:0',
            'stock'     => 'required|integer|min:0',
            'image'     => 'nullable|url',
            'is_rental' => 'boolean',
        ]);

        $product->update([
            'name'      => $request->name,
            'category'  => $request->category,
            'price'     => $request->price,
            'stock'     => $request->stock,
            'image'     => $request->image ?? $product->image,
            'is_rental' => $request->boolean('is_rental', false),
        ]);

        return response()->json(['success' => true, 'message' => 'Produk berhasil diupdate!', 'data' => $product]);
    }

    // 9. Hapus Produk
    public function deleteProduct(Request $request, $id)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);

        $product = Product::where('id', $id)->where('venue_id', $venue->id)->first();

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan atau bukan milik Anda.'], 404);
        }

        $product->delete();
        return response()->json(['success' => true, 'message' => 'Produk berhasil dihapus.']);
    }

    // 10. Toggle status rental produk (is_rental true/false)
    public function toggleRental(Request $request, $id)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);

        $product = Product::where('id', $id)->where('venue_id', $venue->id)->first();
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan atau bukan milik Anda.'], 404);
        }
        $product->is_rental = !$product->is_rental;
        $product->save();
        return response()->json([
            'success' => true,
            'message' => 'Status rental diupdate.',
            'is_rental' => $product->is_rental
        ]);
    }

    // 11. Ambil detail venue milik admin yang sedang login
    public function getMyVenue(Request $request)
    {
        $venue = $this->getAdminVenue($request);

        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 404);
        }

        return response()->json(['success' => true, 'data' => $venue->load('courts')]);
    }

    // 11b. Update jam operasional venue (open_time & close_time)
    public function updateVenueHours(Request $request)
    {
        $request->validate([
            'open_time'  => 'required|date_format:H:i',
            'close_time' => 'required|date_format:H:i|after:open_time',
        ]);

        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 404);
        }

        $venue->update([
            'open_time'  => $request->open_time . ':00',   // Simpan dengan detik (HH:MM:SS)
            'close_time' => $request->close_time . ':00',
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Jam operasional berhasil diperbarui!',
            'data'     => $venue,
        ]);
    }

    // 12. Ambil daftar order kantin untuk venue admin
    public function getOrders(Request $request)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 403);
        }

        $orders = \App\Models\Order::with('items.product')
            ->where('venue_id', $venue->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    // 13. Selesaikan order kantin (Admin)
    public function completeOrder(Request $request, $id)
    {
        $venue = $this->getAdminVenue($request);
        if (!$venue) {
            return response()->json(['success' => false, 'message' => 'Anda belum memiliki venue terdaftar.'], 403);
        }

        $order = \App\Models\Order::where('id', $id)->where('venue_id', $venue->id)->first();
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan atau bukan milik venue Anda.'], 404);
        }

        if ($order->status !== 'ready_to_pickup') {
            return response()->json([
                'success' => false, 
                'message' => 'Pesanan belum dibayar atau sudah selesai. Hanya pesanan dengan status "Siap Diambil" yang bisa diselesaikan.'
            ], 400);
        }

        $order->status = 'completed';
        $order->save();

        return response()->json(['success' => true, 'message' => 'Pesanan ditandai selesai.']);
    }
}
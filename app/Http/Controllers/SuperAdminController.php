<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use App\Models\Venue;
use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    // Global Analisis
    public function getAnalytics()
    {
        $totalVenues   = Venue::where('status', 'active')->count();
        $totalUsers    = User::where('role', 'user')->count();
        $totalAdmins   = User::where('role', 'admin')->count();

        // Gross Revenue Volume: Total omzet dari booking + orders
        $bookingRevenue = Booking::whereIn('status', ['paid', 'completed', 'settled', 'capture', 'success'])->sum('total_price');
        $orderRevenue   = Order::where('status', 'completed')->sum('total_price');
        $totalGRV       = $bookingRevenue + $orderRevenue;

        // Booking 7 hari terakhir
        $recentBookings = Booking::where('created_at', '>=', Carbon::now()->subDays(7))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'), DB::raw('SUM(total_price) as revenue'))
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_venues'   => $totalVenues,
                'total_users'    => $totalUsers,
                'total_admins'   => $totalAdmins,
                'total_grv'      => (int) $totalGRV,
                'recent_bookings' => $recentBookings,
            ]
        ]);
    }

    // 2. MANAJEMEN VENUE (GOR)
    public function getVenues()
    {
        $venues = Venue::with('owner')->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $venues]);
    }

    public function storeVenue(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'kategori'        => 'required|string',
            'lokasi'          => 'required|string',
            'phone'           => 'nullable|string',
            // Admin baru
            'admin_name'      => 'required|string',
            'admin_email'     => 'required|email|unique:users,email',
            'admin_password'  => 'required|string|min:6',
        ]);

        // [SECURITY PATCH] TUGAS 3: Bungkus SELURUH operasi insert dalam DB Transaction.
        // Jika salah satu langkah gagal (buat venue OR buat admin), semua di-rollback.
        // Ini mencegah terbentuknya "Orphaned Venue" tanpa pemilik di database.
        try {
            DB::beginTransaction();

            // 1. Buat venue dulu
            $venue = Venue::create([
                'name'             => $request->name,
                'kategori'         => $request->kategori,
                'lokasi'           => $request->lokasi,
                'phone'            => $request->phone,
                'harga_mulai'      => 0,
                'rating'           => '0.0',
                'fasilitas_utama'  => [],
                'fasilitas_lengkap'=> [],
                'peraturan'        => [],
                'status'           => 'active',
            ]);

            // 2. Buat user Admin baru
            $admin = User::create([
                'name'     => $request->admin_name,
                'email'    => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'role'     => 'admin',
                'is_admin' => true,
            ]);

            // 3. Tautkan venue ke admin sebagai owner
            $venue->update(['owner_id' => $admin->id]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "GOR '{$venue->name}' & Admin '{$admin->name}' berhasil dibuat.",
                'data'    => ['venue' => $venue, 'admin' => $admin]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat venue dan admin: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateVenue(Request $request, $id)
    {
        $venue = Venue::findOrFail($id);
        $request->validate([
            'name'     => 'sometimes|string',
            'lokasi'   => 'sometimes|string',
            'phone'    => 'nullable|string',
            'kategori' => 'sometimes|string',
        ]);
        $venue->update($request->only(['name', 'lokasi', 'phone', 'kategori', 'image']));
        return response()->json(['success' => true, 'data' => $venue]);
    }

    public function toggleVenueStatus($id)
    {
        $venue = Venue::findOrFail($id);
        $venue->status = ($venue->status === 'active') ? 'suspended' : 'active';
        $venue->save();
        return response()->json(['success' => true, 'message' => "Status GOR diubah menjadi '{$venue->status}'.", 'data' => $venue]);
    }

    public function destroyVenue($id)
    {
        $venue = Venue::findOrFail($id);
        
        // Hapus owner_id agar tidak bentrok dengan relasi jika user dihapus terpisah,
        // Tapi pada migrasi ini cascade seharusnya menangani relasi,
        // atau kita bisa hapus venue-nya secara langsung.
        $venue->delete();
        
        return response()->json(['success' => true, 'message' => 'Venue berhasil dihapus.']);
    }

    // 3. MANAJEMEN USER
    public function getUsers(Request $request)
    {
        $query = User::where('role', 'user')->orderBy('created_at', 'desc');
        if ($request->filled('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }
        $users = $query->paginate(20);
        return response()->json(['success' => true, 'data' => $users]);
    }

    public function toggleUserBan($id)
    {
        $user = User::findOrFail($id);
        // Gunakan kolom email_verified_at sebagai penanda ban (null = banned)
        // Lebih baik: kita pakai kolom khusus, tapi untuk MVP ini cukup
        if ($user->role === 'banned') {
            $user->role = 'user';
            $msg = "User '{$user->name}' berhasil di-unban.";
        } else {
            $user->role = 'banned';
            $msg = "User '{$user->name}' berhasil di-ban.";
        }
        $user->save();
        return response()->json(['success' => true, 'message' => $msg]);
    }
}
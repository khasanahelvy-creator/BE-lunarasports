<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VenueController; 
use App\Http\Controllers\BookingController; 
use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController; 
use App\Http\Controllers\AuthController; 
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\MabarController;
use App\Http\Controllers\ProfileController;
use App\Models\Court;

// ==========================================
// RUTE PUBLIC (Tanpa Token)
// ==========================================
Route::get('/courts', function () {
    // Eager-load relasi 'venue' agar open_time & close_time ikut dalam response
    // (digunakan BookingModal untuk generate slot waktu secara dinamis)
    $courts = \App\Models\Court::with('venue:id,open_time,close_time,name')->get();
    return response()->json(['success' => true, 'data' => $courts]);
});
Route::get('/users/{id}/profile', [ProfileController::class, 'getPublicProfile']);

Route::get('/auth/google', [GoogleAuthController::class, 'redirect']);
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback']);

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::get('/venues', [VenueController::class, 'index']);
Route::get('/products', [ProductController::class, 'index']);
// ==========================================
// WEBHOOK MIDTRANS — BEBAS DARI SEMUA MIDDLEWARE
// PENTING: Midtrans mengirim POST dari server mereka (tanpa token user).
// Harus berada di luar grup auth:sanctum maupun middleware apapun.
// ==========================================
Route::withoutMiddleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/midtrans-callback',  [OrderController::class,   'callback']);           // Callback pembelian barang (Market)
    Route::post('/booking-callback',   [BookingController::class, 'midtransCallback']);   // Callback booking lapangan
});


// ==========================================
// RUTE PROTECTED (Wajib Token Sanctum - User Biasa)
// ==========================================
Route::middleware('auth:sanctum')->group(function () {
    // Data User
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::get('/user/profile', [ProfileController::class, 'getProfile']);
    Route::put('/user/profile', [ProfileController::class, 'updateProfile']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);

    // Pemesanan & Transaksi
    Route::post('/orders', [OrderController::class, 'store']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::get('/history', [BookingController::class, 'history']);
    Route::get('/user/orders', [OrderController::class, 'userHistory']);

    // [TUGAS 2] Sync status pembayaran secara manual via Midtrans API
    Route::post('/bookings/{id}/sync-status', [BookingController::class, 'syncPaymentStatus']);


    // Mabar (Butuh Login)
    Route::get('/mabars', [MabarController::class, 'index']);
    Route::get('/mabars/my-mabars', [MabarController::class, 'myMabars']);
    Route::post('/mabars', [MabarController::class, 'store']);
    Route::post('/mabars/{id}/join', [MabarController::class, 'join']);
    Route::post('/mabars/{mabar}/approve/{user}', [MabarController::class, 'approve']);

    // Vouchers (User Validation)
    Route::post('/vouchers/validate', [\App\Http\Controllers\VoucherController::class, 'validateVoucher']);
});

// ==========================================
// RUTE SUPERADMIN (Wajib role superadmin)
// ==========================================
Route::middleware(['auth:sanctum', 'check.superadmin'])->prefix('superadmin')->group(function () {
    // Analytics Global
    Route::get('/analytics', [\App\Http\Controllers\SuperAdminController::class, 'getAnalytics']);

    // Manajemen GOR / Venue
    Route::get('/venues', [\App\Http\Controllers\SuperAdminController::class, 'getVenues']);
    Route::post('/venues', [\App\Http\Controllers\SuperAdminController::class, 'storeVenue']);
    Route::put('/venues/{id}', [\App\Http\Controllers\SuperAdminController::class, 'updateVenue']);
    Route::delete('/venues/{id}', [\App\Http\Controllers\SuperAdminController::class, 'destroyVenue']);
    Route::post('/venues/{id}/toggle-status', [\App\Http\Controllers\SuperAdminController::class, 'toggleVenueStatus']);

    // Manajemen User
    Route::get('/users', [\App\Http\Controllers\SuperAdminController::class, 'getUsers']);
    Route::post('/users/{id}/toggle-ban', [\App\Http\Controllers\SuperAdminController::class, 'toggleUserBan']);

    // Kelola Voucher Global
    Route::get('/vouchers', [\App\Http\Controllers\VoucherController::class, 'index']);
    Route::post('/vouchers', [\App\Http\Controllers\VoucherController::class, 'store']);
    Route::put('/vouchers/{id}', [\App\Http\Controllers\VoucherController::class, 'update']);
    Route::delete('/vouchers/{id}', [\App\Http\Controllers\VoucherController::class, 'destroy']);
    Route::post('/vouchers/{id}/toggle', [\App\Http\Controllers\VoucherController::class, 'toggleActive']);
});

// ==========================================
// RUTE KHUSUS ADMIN (Wajib Token Sanctum + is_admin = true)
// ==========================================
Route::middleware(['auth:sanctum', 'check.admin'])->prefix('admin')->group(function () {
    Route::get('/bookings', [AdminController::class, 'getAllBookings']);
    Route::post('/verify-ticket', [AdminController::class, 'verifyTicket']);
    Route::post('/checkin', [AdminController::class, 'checkInTicket']);
    Route::get('/weekly-revenue', [AdminController::class, 'getWeeklyRevenue']);
    Route::get('/courts', [AdminController::class, 'getCourts']);
    Route::post('/courts', [AdminController::class, 'addCourt']);
    Route::put('/courts/{id}', [AdminController::class, 'updateCourt']);
    Route::delete('/courts/{id}', [AdminController::class, 'deleteCourt']);

    // Rute Manajemen Produk / Toko
    Route::get('/products', [AdminController::class, 'getProducts']);
    Route::post('/products', [AdminController::class, 'storeProduct']);
    Route::put('/products/{id}', [AdminController::class, 'updateProduct']);
    Route::delete('/products/{id}', [AdminController::class, 'deleteProduct']);
    Route::patch('/products/{id}/toggle-rental', [AdminController::class, 'toggleRental']);

    // Rute Info Venue Admin yang sedang login
    Route::get('/my-venue', [AdminController::class, 'getMyVenue']);
    Route::patch('/my-venue/hours', [AdminController::class, 'updateVenueHours']); // Update jam operasional

    // Manajemen Order Market (Click & Collect)
    Route::get('/orders', [\App\Http\Controllers\AdminController::class, 'getOrders']);
    Route::patch('/orders/{id}/complete', [\App\Http\Controllers\AdminController::class, 'completeOrder']);
});
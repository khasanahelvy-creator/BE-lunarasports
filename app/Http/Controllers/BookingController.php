<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Court;
use App\Models\Product;
use App\Jobs\SendWhatsAppTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Midtrans\Config;
use Midtrans\Snap;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        // 1. Validasi data wajib
        $validator = Validator::make($request->all(), [
            'court_id'       => 'required|exists:courts,id',
            'customer_name'  => 'required|string|max:255',
            'customer_email' => 'required|email',
            'customer_phone' => 'required|string|max:20',
            'booking_date'   => 'required|date',
            'time_slots'     => 'required|array',
            'subtotal'       => 'required|numeric',
            'total_price'    => 'required|numeric',
            'payment_method' => 'required|string',
            // addons bersifat opsional
            'addons'         => 'nullable|array',
            'addons.*.product_id' => 'required_with:addons|exists:products,id',
            'addons.*.qty'   => 'required_with:addons|integer|min:1',
            'addons.*.price' => 'required_with:addons|numeric',
            'addons.*.name'  => 'required_with:addons|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data pesanan tidak valid atau kurang lengkap.',
                'errors'  => $validator->errors()
            ], 422);
        }

        // 2. Cek bentrok waktu
        $bentrok = Booking::where('court_id', $request->court_id)
            ->where('booking_date', $request->booking_date)
            ->whereIn('status', ['pending', 'paid', 'settlement'])
            ->where(function ($query) use ($request) {
                foreach ($request->time_slots as $slot) {
                    $query->orWhereJsonContains('time_slots', $slot);
                }
            })
            ->exists();

        if ($bentrok) {
            return response()->json([
                'success' => false,
                'message' => 'Waduh, salah satu jam yang kamu pilih sudah di-booking orang. Silakan pilih jam lain!'
            ], 409);
        }

        // ================================================================
        // [SECURITY PATCH] TUGAS 1: Hitung semua harga di sisi SERVER.
        // Jangan pernah percaya nilai subtotal / admin_fee dari Frontend.
        // ================================================================

        // 3a. Ambil data Court dari DB untuk mendapatkan harga ASLI
        $court = Court::findOrFail($request->court_id);

        // 3b. Hitung subtotal sewa lapangan = harga per jam (DB) x jumlah slot
        $calculatedSubtotal = (int) $court->harga * count($request->time_slots);

        // 3c. Hitung total addons langsung dari database (Anti Tampering)
        $addons = $request->addons ?? [];
        $addonsTotal = 0;
        foreach ($addons as &$addon) {
            $product = Product::find($addon['product_id']);
            if ($product) {
                $addon['price'] = (int) $product->price; // Pakai harga DB, bukan dari request
                $addonsTotal += $addon['price'] * (int) $addon['qty'];
            }
        }
        unset($addon); // Mencegah bug reference di PHP pada foreach berikutnya

        // 3d. Admin fee adalah konstanta server-side, tidak boleh dikirm dari frontend
        $platformFee = 2500;

        $discountAmount = 0;
        $voucherCode = null;

        // Subtotal yang eligible untuk diskon (lapangan + addon)
        $eligibleSubtotal = $calculatedSubtotal + $addonsTotal;

        if ($request->filled('voucher_code')) {
            $voucher = \App\Models\Voucher::where('code', strtoupper($request->voucher_code))
                ->where('is_active', true)
                ->first();

            if ($voucher && (!$voucher->expires_at || \Carbon\Carbon::now()->lessThanOrEqualTo($voucher->expires_at))) {
                if ($eligibleSubtotal >= $voucher->min_transaction) {
                    $voucherCode = $voucher->code;
                    if ($voucher->discount_type === 'percent') {
                        $discountAmount = ($eligibleSubtotal * $voucher->discount_value) / 100;
                        if ($voucher->max_discount && $discountAmount > $voucher->max_discount) {
                            $discountAmount = $voucher->max_discount;
                        }
                    } else {
                        $discountAmount = $voucher->discount_value;
                    }
                    if ($discountAmount > $eligibleSubtotal) {
                        $discountAmount = $eligibleSubtotal;
                    }
                }
            }
        }

        // 3e. Hitung gross_amount total secara penuh di server:
        //     subtotal sewa (server) + total addons (server) + platform fee (server) - diskon
        $grossAmount = $calculatedSubtotal + $addonsTotal + $platformFee - (int) $discountAmount;

        // 4. Simpan ke database (menggunakan nilai yang sudah dihitung server)
        $booking = Booking::create([
            'user_id'        => auth()->id(),
            'venue_id'       => $court->venue_id,
            'court_id'       => $request->court_id,
            'customer_name'  => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'booking_date'   => $request->booking_date,
            'time_slots'     => $request->time_slots,
            'subtotal'       => $calculatedSubtotal,  // [PATCH] Pakai nilai kalkulasi server
            'admin_fee'      => $platformFee,          // [PATCH] Konstanta server
            'total_price'    => $grossAmount,
            'payment_method' => $request->payment_method,
            'status'         => 'pending',
            'addons'         => count($addons) > 0 ? $addons : null,
            'addons_total'   => $addonsTotal,
            'voucher_code'   => $voucherCode,
            'discount'       => $discountAmount,
        ]);

        // 5. Konfigurasi Midtrans
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', false);
        Config::$isSanitized = true;
        Config::$is3ds = true;
        Config::$curlOptions = [
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => []
        ];

        // 6. Build item details untuk Midtrans
        //    [PATCH] Semua nilai (price) kini dari hasil kalkulasi server, bukan request
        $itemDetails = [
            [
                'id'       => 'COURT-' . $court->id,
                'price'    => $calculatedSubtotal,   // [PATCH] Nilai dari DB
                'quantity' => 1,
                'name'     => 'Sewa Lapangan ' . $court->name . ' (' . count($request->time_slots) . ' Jam)',
            ]
        ];

        if ($discountAmount > 0) {
            $itemDetails[] = [
                'id'       => 'DISCOUNT',
                'price'    => -((int) $discountAmount),
                'quantity' => 1,
                'name'     => 'Diskon Voucher (' . $voucherCode . ')',
            ];
        }

        // Tambahkan tiap addon ke item_details
        foreach ($addons as $addon) {
            $itemDetails[] = [
                'id'       => 'ADDON-' . $addon['product_id'],
                'price'    => (int) $addon['price'],   // Sudah diambil dari DB di atas
                'quantity' => (int) $addon['qty'],
                'name'     => substr($addon['name'], 0, 50),
            ];
        }

        // Tambahkan platform fee (konstanta server)
        if ($platformFee > 0) {
            $itemDetails[] = [
                'id'       => 'PLATFORM-FEE',
                'price'    => $platformFee,             // [PATCH] Nilai dari konstanta server
                'quantity' => 1,
                'name'     => 'Biaya Layanan Platform',
            ];
        }

        $snapOrderId = 'LNR-' . uniqid() . '-' . $booking->id;

        $params = [
            'transaction_details' => [
                'order_id'     => $snapOrderId,
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => $booking->customer_name,
                'email'      => $booking->customer_email,
                'phone'      => $booking->customer_phone,
            ],
        ];

        try {
            $snapToken = Snap::getSnapToken($params);

            // Simpan snap_order_id ke DB agar bisa dipakai untuk sync status nanti
            $booking->update(['snap_order_id' => $snapOrderId]);

            return response()->json([
                'success'    => true,
                'message'    => 'Booking berhasil dibuat.',
                'snap_token' => $snapToken,
                'data'       => $booking
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke gateway pembayaran: ' . $e->getMessage()
            ], 500);
        }
    }

    public function midtransCallback(Request $request)
    {
        $serverKey = env('MIDTRANS_SERVER_KEY');

        $orderId           = $request->order_id;
        $statusCode        = $request->status_code;
        $grossAmount       = $request->gross_amount;
        $transactionStatus = $request->transaction_status;
        $signatureKey      = $request->signature_key;

        // Validasi keamanan signature SHA512
        $mySignature = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        if ($mySignature !== $signatureKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Signature!'
            ], 403);
        }

        // Ekstrak ID Booking asli dari format "LNR-xxxxxx-ID"
        $parts     = explode('-', $orderId);
        $bookingId = end($parts);
        $booking   = Booking::find($bookingId);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking tidak ditemukan'], 404);
        }

        if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
            $booking->update(['status' => 'paid']);

            // [SECURITY PATCH] TUGAS 2: Kurangi stok add-on dengan validasi anti-minus
            if ($booking->addons && count($booking->addons) > 0) {
                foreach ($booking->addons as $addon) {
                    $product = Product::find($addon['product_id']);
                    if ($product) {
                        $qty = (int) $addon['qty'];
                        if ($product->stock >= $qty) {
                            // Stok mencukupi: decrement normal
                            $product->decrement('stock', $qty);
                        } elseif ($product->stock > 0) {
                            // Stok ada tapi kurang dari qty yang dipesan: habiskan sisa stok
                            Log::warning('[STOCK_ALERT] Stok tidak cukup pada booking_id=' . $booking->id . ', product_id=' . $product->id . '. Stok tersisa: ' . $product->stock . ', qty dipesan: ' . $qty . '. Stok diarahkan ke 0.');
                            $product->stock = 0;
                            $product->save();
                        } else {
                            // Stok sudah 0, tidak bisa dikurangi lagi
                            Log::warning('[STOCK_ALERT] Produk product_id=' . $product->id . ' stok sudah 0 saat callback booking_id=' . $booking->id . '. Tidak ada pengurangan dilakukan.');
                        }
                    }
                }
            }

            $targetPhone = $booking->customer_phone;
            $timeSlots   = is_array($booking->time_slots)
                ? implode(', ', $booking->time_slots)
                : $booking->time_slots;

            $addonsText = '';
            if ($booking->addons && count($booking->addons) > 0) {
                $addonsText = "\n\n🛍️ *Add-on yang dipesan:*\n";
                foreach ($booking->addons as $addon) {
                    $addonsText .= "- {$addon['name']} x{$addon['qty']}\n";
                }
                $addonsText .= "(Ambil di meja kasir GOR saat tiba)";
            }

            $waMessage = "✅ *PEMBAYARAN BERHASIL*\n\n"
                       . "Halo *{$booking->customer_name}*,\n"
                       . "Tiket lapangan kamu di Lunara Sports sudah terbit! Berikut detailnya:\n\n"
                       . "🎫 *Order ID:* LNR-{$booking->id}\n"
                       . "📅 *Tanggal:* {$booking->booking_date}\n"
                       . "⏰ *Jam:* {$timeSlots}"
                       . $addonsText . "\n\n"
                       . "Tunjukkan pesan ini atau QR Code di website kepada petugas GOR untuk verifikasi kedatangan.\n\n"
                       . "Selamat bermain! 🏸⚽";

            SendWhatsAppTicket::dispatch($targetPhone, $waMessage);

        } elseif ($transactionStatus === 'cancel' || $transactionStatus === 'deny' || $transactionStatus === 'expire') {
            $booking->update(['status' => 'cancelled']);
        } elseif ($transactionStatus === 'pending') {
            $booking->update(['status' => 'pending']);
        }

        return response()->json(['success' => true, 'message' => 'Status booking berhasil diupdate']);
    }

    public function history(Request $request)
    {
        $user = $request->user();

        $bookings = Booking::with('court')
            ->where('customer_email', $user->email)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $bookings
        ]);
    }

    /**
     * [TUGAS 2] SYNC STATUS PEMBAYARAN MANUAL
     *
     * Endpoint ini dipanggil oleh user dari ProfilePage jika status masih
     * "pending" padahal mereka sudah bayar. Fungsi ini melakukan HTTP GET
     * ke Midtrans API untuk mengecek status riil transaksi.
     *
     * POST /api/bookings/{id}/sync-status   (Protected: auth:sanctum)
     */
    public function syncPaymentStatus(Request $request, $id)
    {
        $booking = Booking::find($id);

        if (!$booking) {
            return response()->json(['success' => false, 'message' => 'Booking tidak ditemukan.'], 404);
        }

        // Pastikan hanya pemilik booking yang bisa sync
        if ($booking->customer_email !== $request->user()->email) {
            return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
        }

        if ($booking->status === 'paid') {
            return response()->json([
                'success' => true,
                'already_paid' => true,
                'message' => 'Pembayaran sudah dikonfirmasi sebelumnya.',
                'data'    => $booking,
            ]);
        }

        // Gunakan snap_order_id yang disimpan saat booking dibuat (jika ada)
        // Fallback ke pattern lama jika kolom belum tersedia (booking lama)
        $orderIdToCheck = $booking->snap_order_id ?? ('LNR-LUNARASPORTS-' . $booking->id);

        if (!$booking->snap_order_id) {
            Log::warning('[SYNC_PAYMENT] Booking #' . $booking->id . ' tidak memiliki snap_order_id. Menggunakan fallback: ' . $orderIdToCheck);
        }

        try {
            $httpResponse = \Illuminate\Support\Facades\Http::withHeaders([
                'Authorization' => $authHeader,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ])->get("https://api.sandbox.midtrans.com/v2/{$orderIdToCheck}/status");

            if ($httpResponse->failed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Gagal terhubung ke server Midtrans. Coba lagi sebentar.',
                ], 502);
            }

            $midtransData      = $httpResponse->json();
            $transactionStatus = $midtransData['transaction_status'] ?? 'unknown';

            Log::info('[SYNC_PAYMENT] Booking #' . $booking->id . ' — Midtrans status: ' . $transactionStatus);

            if (in_array($transactionStatus, ['capture', 'settlement'])) {
                $booking->update(['status' => 'paid']);

                return response()->json([
                    'success'  => true,
                    'updated'  => true,
                    'message'  => '✅ Pembayaran dikonfirmasi! Status booking diperbarui menjadi Lunas.',
                    'data'     => $booking->fresh(),
                ]);
            }

            // Status lain (pending, expire, cancel, dll)
            return response()->json([
                'success'            => true,
                'updated'            => false,
                'midtrans_status'    => $transactionStatus,
                'message'            => 'Status pembayaran dari Midtrans: ' . $transactionStatus . '. Belum dikonfirmasi lunas.',
                'data'               => $booking,
            ]);

        } catch (\Exception $e) {
            Log::error('[SYNC_PAYMENT] Exception untuk booking #' . $booking->id . ': ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghubungi Midtrans: ' . $e->getMessage(),
            ], 500);
        }
    }
}
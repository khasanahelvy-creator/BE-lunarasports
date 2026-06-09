<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
// Tambahkan import Snap Midtrans
use Midtrans\Snap;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_name'  => 'required|string',
            'customer_email' => 'required|email',
            'total_price'    => 'required|numeric',
            'items'          => 'required|array',
            'items.*.id'     => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price'  => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            $order = Order::create([
                'customer_name'  => $request->customer_name,
                'customer_email' => $request->customer_email,
                'total_price'    => $request->total_price,
                'payment_method' => 'qris',
                'status'         => 'pending'
            ]);

            foreach ($request->items as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item['id'],
                    'quantity'   => $item['quantity'],
                    'price'      => $item['price']
                ]);
            }

            DB::commit();

            // ==========================================
            // KODE BARU: Meminta Snap Token ke Midtrans
            // ==========================================
            $params = array(
                'transaction_details' => array(
                    // Tambahkan waktu di belakang ID agar unik setiap transaksi
                    'order_id' => 'STORE-' . $order->id . '-' . time(),
                    'gross_amount' => $order->total_price,
                ),
                'customer_details' => array(
                    'first_name' => $order->customer_name,
                    'email' => $order->customer_email,
                ),
            );

            // Minta tiket ke server Midtrans
            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat!',
                'snap_token' => $snapToken, // Kirim tiket ini ke React!
                'data'    => $order->load('items')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat pesanan.',
                'error'   => $e->getMessage()
            ], 500);
        }
    }
    // ==========================================
    // KODE BARU: Webhook / Callback Midtrans
    // ==========================================
    public function callback(Request $request)
    {
        try {
            // Kita ambil data langsung dari request (bypass pengecekan ketat SDK untuk testing)
            $transactionStatus = $request->transaction_status;
            $orderIdFull = $request->order_id; 
            
            // Jika data kosong, tolak
            if (!$orderIdFull || !$transactionStatus) {
                return response()->json(['message' => 'Data tidak lengkap'], 400);
            }

            // Kita pecah ID-nya untuk mendapatkan angka ID asli di database
            // Pisahkan berdasarkan tanda '-' lalu ambil bagian kedua (index 1)
            $orderIdParts = explode('-', $orderIdFull);
            $realOrderId = $orderIdParts[1];
            
            $order = Order::find($realOrderId);
            
            if (!$order) {
                return response()->json(['message' => 'Order tidak ditemukan'], 404);
            }

            // Update status berdasarkan laporan
            if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
                $order->status = 'paid'; // Lunas
            } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
                $order->status = 'failed'; // Gagal/Batal
            } else if ($transactionStatus == 'pending') {
                $order->status = 'pending'; // Masih nunggu
            }

            $order->save();

            return response()->json([
                'success' => true, 
                'message' => 'Status pesanan berhasil diupdate menjadi: ' . $order->status
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }
    // ==========================================
    // KODE BARU: Menarik Riwayat Pesanan User
    // ==========================================
    public function history(Request $request)
    {
        // Ambil email dari parameter URL
        $email = $request->query('email');

        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email dibutuhkan'], 400);
        }

        // Cari semua pesanan milik email tersebut, urutkan dari yang terbaru
        // Kita juga menarik relasi 'items' dan 'product' sekaligus agar bisa menampilkan gambar/nama barang
        $orders = Order::with('items.product')
            ->where('customer_email', $email)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }
}
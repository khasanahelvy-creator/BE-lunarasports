<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Midtrans\Config;
use Midtrans\Snap;
use App\Jobs\SendWhatsAppTicket;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'customer_name'    => 'required|string',
            'customer_email'   => 'required|email',
            'customer_phone'   => 'nullable|string',
            'total_price'      => 'required|numeric',
            'items'            => 'required|array|min:1',
            'items.*.id'       => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price'    => 'required|numeric',
        ]);

        try {
            DB::beginTransaction();

            // Ambil venue_id dari produk pertama
            $firstProduct = Product::find($request->items[0]['id']);

            $order = Order::create([
                'venue_id'       => $firstProduct ? $firstProduct->venue_id : null,
                'customer_name'  => $request->customer_name,
                'customer_email' => $request->customer_email,
                'customer_phone' => $request->customer_phone,
                'total_price'    => $request->total_price,
                'payment_method' => 'qris',
                'status'         => 'unpaid',  // Status GOR: unpaid -> ready_to_pickup -> completed
            ]);

            $realTotal = 0;
            $itemDetails = [];
            foreach ($request->items as $item) {
                $product = Product::find($item['id']);
                if (!$product) {
                    throw new \Exception("Produk ID {$item['id']} tidak ditemukan.");
                }

                $realPrice = (int) $product->price;
                $quantity  = (int) $item['quantity'];
                $subtotal  = $realPrice * $quantity;
                $realTotal += $subtotal;

                OrderItem::create([
                    'order_id'      => $order->id,
                    'product_id'    => $product->id,
                    'quantity'      => $quantity,
                    'price'         => $realPrice,
                ]);

                $itemDetails[] = [
                    'id'       => 'PROD-' . $product->id,
                    'price'    => $realPrice,
                    'quantity' => $quantity,
                    'name'     => $product->name,
                ];
            }

            // Perbarui total_price order dengan perhitungan asli server (Anti Tampering)
            $order->total_price = $realTotal;
            $order->save();

            DB::commit();

            // Konfigurasi Midtrans
            Config::$serverKey       = env('MIDTRANS_SERVER_KEY');
            Config::$isProduction    = env('MIDTRANS_IS_PRODUCTION', false);
            Config::$isSanitized     = true;
            Config::$is3ds           = true;
            Config::$curlOptions     = [
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTPHEADER     => []
            ];

            $params = [
                'transaction_details' => [
                    'order_id'     => 'STORE-' . $order->id . '-' . time(),
                    'gross_amount' => $realTotal,
                ],
                'customer_details' => [
                    'first_name' => $order->customer_name,
                    'email'      => $order->customer_email,
                ],
                'item_details' => $itemDetails,
            ];

            $snapToken = Snap::getSnapToken($params);

            return response()->json([
                'success'    => true,
                'message'    => 'Pesanan berhasil dibuat!',
                'snap_token' => $snapToken,
                'data'       => $order->load('items')
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

    //  Callback dari Midtrans setelah pembayaran pesanan kantin.
    //  Validasi signature SHA512, update status, dan kurangi stok produk.     
    public function callback(Request $request)
    {
        try {
            $serverKey         = env('MIDTRANS_SERVER_KEY');
            $transactionStatus = $request->transaction_status;
            $orderIdFull       = $request->order_id;
            $statusCode        = $request->status_code;
            $grossAmount       = $request->gross_amount;
            $signatureKey      = $request->signature_key;

            if (!$orderIdFull || !$transactionStatus) {
                return response()->json(['message' => 'Data tidak lengkap'], 400);
            }

            // Validasi keamanan signature SHA512
            if ($signatureKey) {
                $mySignature = hash('sha512', $orderIdFull . $statusCode . $grossAmount . $serverKey);
                if ($mySignature !== $signatureKey) {
                    return response()->json(['success' => false, 'message' => 'Invalid Signature!'], 403);
                }
            }

            // Format order_id: STORE-{id}-{timestamp}
            $orderIdParts = explode('-', $orderIdFull);
            $realOrderId  = $orderIdParts[1] ?? null;
            $order        = Order::with('items')->find($realOrderId);

            if (!$order) {
                return response()->json(['message' => 'Order tidak ditemukan'], 404);
            }

            if ($transactionStatus === 'capture' || $transactionStatus === 'settlement') {
                // Bayar sukses → siap diambil di kasir GOR
                $order->status = 'ready_to_pickup';
                $order->save();

                // [SECURITY PATCH] TUGAS 2: Kurangi stok produk kantin dengan validasi anti-minus
                $itemsListText = "";
                foreach ($order->items as $item) {
                    $product = Product::find($item->product_id);
                    if ($product) {
                        $qty = (int) $item->quantity;
                        if ($product->stock >= $qty) {
                            // Stok mencukupi: decrement normal
                            $product->decrement('stock', $qty);
                        } elseif ($product->stock > 0) {
                            // Stok ada tapi kurang dari qty yang dipesan: habiskan sisa stok
                            Log::warning('[STOCK_ALERT] Stok tidak cukup pada order_id=' . $order->id . ', product_id=' . $product->id . '. Stok tersisa: ' . $product->stock . ', qty dipesan: ' . $qty . '. Stok diarahkan ke 0.');
                            $product->stock = 0;
                            $product->save();
                        } else {
                            // Stok sudah 0, tidak bisa dikurangi lagi
                            Log::warning('[STOCK_ALERT] Produk product_id=' . $product->id . ' stok sudah 0 saat callback order_id=' . $order->id . '. Tidak ada pengurangan dilakukan.');
                        }
                        $itemsListText .= "- {$product->name} (x{$qty})\n";
                    }
                }

                // Kirim notifikasi WA (Fonnte)
                if ($order->customer_phone) {
                    $waMessage = "✅ *PEMBAYARAN KANTIN LUNAS*\n\n"
                               . "Halo *{$order->customer_name}*,\n"
                               . "Pesanan Kantin/Marketplace kamu sudah lunas!\n\n"
                               . "🎫 *Order ID:* LNR-{$order->id}\n"
                               . "🛍️ *Detail Pesanan:*\n{$itemsListText}\n"
                               . "Tunjukkan pesan ini di meja kasir GOR untuk mengambil pesananmu.\n\n"
                               . "Terima kasih! 🏸⚽";
                    SendWhatsAppTicket::dispatch($order->customer_phone, $waMessage);
                }

            } elseif ($transactionStatus === 'cancel' || $transactionStatus === 'deny' || $transactionStatus === 'expire') {
                $order->status = 'unpaid';
                $order->save();
            } elseif ($transactionStatus === 'pending') {
                $order->status = 'unpaid';
                $order->save();
            }

            return response()->json([
                'success' => true,
                'message' => 'Status pesanan diupdate: ' . $order->status
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Admin mengkonfirmasi pelanggan sudah mengambil barang.
     */
    public function complete(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order tidak ditemukan'], 404);
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

    public function history(Request $request)
    {
        $email = $request->query('email');

        if (!$email) {
            return response()->json(['success' => false, 'message' => 'Email dibutuhkan'], 400);
        }

        $orders = Order::with('items.product')
            ->where('customer_email', $email)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $orders
        ]);
    }

    /**
     * Mengambil riwayat order (Kantin) untuk user yang login saat ini.
     */
    public function userHistory(Request $request)
    {
        $email = $request->user()->email; // Mengambil email dari Auth Sanctum

        $orders = Order::with('items.product')
            ->where('customer_email', $email)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $orders
        ]);
    }
}
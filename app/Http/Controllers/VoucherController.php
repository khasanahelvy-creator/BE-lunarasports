<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    // Cek apakah voucher valid saat user di checkout
    public function validateVoucher(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'subtotal' => 'required|numeric' // subtotal lapangan + addons (tanpa admin fee)
        ]);

        $voucher = Voucher::where('code', strtoupper($request->code))
            ->where('is_active', true)
            ->first();

        if (!$voucher) {
            return response()->json(['success' => false, 'message' => 'Kode Voucher tidak ditemukan atau tidak aktif.'], 404);
        }

        if ($voucher->expires_at && Carbon::now()->greaterThan($voucher->expires_at)) {
            return response()->json(['success' => false, 'message' => 'Kode Voucher sudah kadaluarsa.'], 400);
        }

        if ($request->subtotal < $voucher->min_transaction) {
            return response()->json([
                'success' => false, 
                'message' => 'Minimal transaksi untuk voucher ini adalah Rp ' . number_format($voucher->min_transaction, 0, ',', '.')
            ], 400);
        }

        // Hitung diskon
        $discount = 0;
        if ($voucher->discount_type === 'percent') {
            $discount = ($request->subtotal * $voucher->discount_value) / 100;
            if ($voucher->max_discount && $discount > $voucher->max_discount) {
                $discount = $voucher->max_discount;
            }
        } else {
            $discount = $voucher->discount_value;
        }

        // Jangan sampai diskon melebihi subtotal
        if ($discount > $request->subtotal) {
            $discount = $request->subtotal;
        }

        return response()->json([
            'success' => true,
            'message' => 'Voucher berhasil digunakan!',
            'data' => [
                'code' => $voucher->code,
                'discount' => (int) $discount,
                'type' => $voucher->discount_type
            ]
        ]);
    }

    // --- CRUD UNTUK SUPERADMIN ---
    public function index()
    {
        $vouchers = Voucher::orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $vouchers]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'venue_id' => 'nullable|exists:venues,id',
            'code' => 'required|string|unique:vouchers,code',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|integer|min:1',
            'max_discount' => 'nullable|integer',
            'min_transaction' => 'nullable|integer',
            'expires_at' => 'nullable|date',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $validated['created_by'] = $request->user()->id;

        $voucher = Voucher::create($validated);

        return response()->json(['success' => true, 'message' => 'Voucher berhasil dibuat.', 'data' => $voucher]);
    }

    public function update(Request $request, $id)
    {
        $voucher = Voucher::findOrFail($id);
        
        $validated = $request->validate([
            'venue_id' => 'nullable|exists:venues,id',
            'code' => 'sometimes|string|unique:vouchers,code,'.$id,
            'discount_type' => 'sometimes|in:percent,fixed',
            'discount_value' => 'sometimes|integer|min:1',
            'max_discount' => 'nullable|integer',
            'min_transaction' => 'nullable|integer',
            'expires_at' => 'nullable|date',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper($validated['code']);
        }

        $voucher->update($validated);

        return response()->json(['success' => true, 'message' => 'Voucher berhasil diperbarui.', 'data' => $voucher]);
    }

    public function destroy($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->delete();
        return response()->json(['success' => true, 'message' => 'Voucher dihapus']);
    }

    public function toggleActive($id)
    {
        $voucher = Voucher::findOrFail($id);
        $voucher->is_active = !$voucher->is_active;
        $voucher->save();
        return response()->json(['success' => true, 'message' => 'Status voucher diubah']);
    }
}
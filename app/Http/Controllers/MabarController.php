<?php

namespace App\Http\Controllers;

use App\Models\Mabar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MabarController extends Controller
{
    /**
     * GET /api/mabars
     * Mengembalikan semua sesi mabar yang berstatus 'open', diurutkan dari yang terbaru,
     * beserta informasi host (nama user pembuat).
     */
    public function index(Request $request)
    {
        $query = Mabar::with(['host:id,name', 'participants' => function($q) {
            $q->select('users.id', 'name')->withPivot('status');
        }])
            ->where('status', 'open')
            ->orderBy('date', 'asc')
            ->orderBy('created_at', 'desc');

        // Filter opsional berdasarkan sport_type jika ada query string ?sport=futsal
        if ($request->filled('sport')) {
            $query->where('sport_type', $request->sport);
        }

        $mabars = $query->get();

        return response()->json([
            'success' => true,
            'data'    => $mabars,
        ]);
    }

    /**
     * GET /api/mabars/my-mabars
     * Mengembalikan daftar mabar yang dibuat oleh host beserta partisipannya.
     */
    public function myMabars(Request $request)
    {
        $mabars = Mabar::with(['host:id,name', 'participants' => function($q) {
                $q->select('users.id', 'name', 'email')->withPivot('status');
            }])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $mabars,
        ]);
    }

    /**
     * POST /api/mabars
     * Membuat sesi mabar baru. Wajib login (dilindungi auth:sanctum di routes).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'            => 'required|string|max:255',
            'sport_type'       => 'required|string|max:100',
            'venue_name'       => 'required|string|max:255',
            'date'             => 'required|date|after_or_equal:today',
            'time'             => 'required|string|max:50',
            'max_participants' => 'required|integer|min:2|max:100',
            'price_per_person' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data mabar tidak valid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $mabar = Mabar::create([
            'user_id'          => $request->user()->id,
            'title'            => $request->title,
            'sport_type'       => $request->sport_type,
            'venue_name'       => $request->venue_name,
            'date'             => $request->date,
            'time'             => $request->time,
            'max_participants' => $request->max_participants,
            'price_per_person' => $request->price_per_person,
            'status'           => 'open',
        ]);

        // Muat relasi host sebelum dikembalikan ke frontend
        $mabar->load('host:id,name');

        return response()->json([
            'success' => true,
            'message' => 'Jadwal mabar berhasil dipublikasikan!',
            'data'    => $mabar,
        ], 201);
    }

    /**
     * POST /api/mabars/{id}/join
     * Request untuk join ke jadwal mabar.
     */
    public function join(Request $request, $id)
    {
        $mabar = Mabar::find($id);

        if (!$mabar) {
            return response()->json(['success' => false, 'message' => 'Jadwal mabar tidak ditemukan.'], 404);
        }

        if ($mabar->status !== 'open') {
            return response()->json(['success' => false, 'message' => 'Jadwal mabar ini sudah ditutup atau penuh.'], 400);
        }

        if ($mabar->user_id === $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Anda adalah host mabar ini, tidak perlu join.'], 400);
        }

        // Cek apakah sudah pernah request
        $existingRequest = $mabar->participants()->where('user_id', $request->user()->id)->first();
        if ($existingRequest) {
            return response()->json(['success' => false, 'message' => 'Anda sudah pernah request join mabar ini.'], 400);
        }

        $mabar->participants()->attach($request->user()->id, ['status' => 'pending']);

        return response()->json(['success' => true, 'message' => 'Request join berhasil dikirim! Menunggu persetujuan host.']);
    }

    /**
     * POST /api/mabars/{mabar}/approve/{user}
     * Host menyetujui ATAU menolak request join dari peserta.
     * Body: { "action": "approve" | "reject" }
     */
    public function approve(Request $request, $mabarId, $userId)
    {
        $mabar = Mabar::find($mabarId);

        if (!$mabar) {
            return response()->json(['success' => false, 'message' => 'Jadwal mabar tidak ditemukan.'], 404);
        }

        // Hanya host yang boleh approve/reject
        if ($mabar->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak untuk mengubah status peserta ini.'], 403);
        }

        $action = $request->input('action', 'approve'); // default: approve

        if ($action === 'reject') {
            $mabar->participants()->updateExistingPivot($userId, ['status' => 'rejected']);
            return response()->json(['success' => true, 'message' => 'Peserta telah ditolak.']);
        }

        // === LOGIKA APPROVE ===
        // Hitung jumlah yang sudah di-approve
        $approvedCount = $mabar->participants()->wherePivot('status', 'approved')->count();
        if ($approvedCount >= $mabar->max_participants) {
            $mabar->update(['status' => 'full']);
            return response()->json(['success' => false, 'message' => 'Mabar sudah penuh!'], 400);
        }

        $mabar->participants()->updateExistingPivot($userId, ['status' => 'approved']);

        // Jika setelah diapprove kuota penuh, update status mabar
        if ($approvedCount + 1 >= $mabar->max_participants) {
            $mabar->update(['status' => 'full']);
        }

        return response()->json(['success' => true, 'message' => 'Peserta berhasil disetujui!']);
    }
}
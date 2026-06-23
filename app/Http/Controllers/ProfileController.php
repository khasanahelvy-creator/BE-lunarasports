<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function getPublicProfile($id)
    {
        $user = User::select('id', 'name', 'avatar', 'created_at', 'role')
            ->withCount(['mabars as hosted_mabars_count'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    public function getProfile(Request $request)
    {
        $user = $request->user();

        // 1. Fetch user's bookings
        $validStatuses = ['paid', 'settled', 'success', 'completed', 'capture'];
        $bookings = Booking::where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('customer_email', $user->email);
            })
            ->whereIn('status', $validStatuses)
            ->with(['court.venue'])
            ->get();

        // 2. Stats Calculation
        $total_matches = $bookings->count();
        
        $total_hours_played = 0;
        foreach ($bookings as $b) {
            $slots = is_string($b->time_slots) ? json_decode($b->time_slots, true) : $b->time_slots;
            if (is_array($slots)) {
                $total_hours_played += count($slots);
            }
        }
        
        $total_calories = $total_hours_played * 350; // 350 kcal per hour
        $total_points = $total_matches * 50; // Dummy points calculation

        // 3. Active Tickets (termasuk yang masih pending untuk dimunculkan di UI)
        $today = Carbon::today()->toDateString();
        $active_tickets = Booking::where(function($query) use ($user) {
                $query->where('user_id', $user->id)
                      ->orWhere('customer_email', $user->email);
            })
            ->whereIn('status', ['pending', 'paid', 'settled', 'success', 'completed', 'capture'])
            ->where('booking_date', '>=', $today)
            ->with(['court.venue'])
            ->orderBy('booking_date', 'asc')
            ->get();

        // Add to user data
        $userData = $user->toArray();
        $userData['total_matches'] = $total_matches;
        $userData['total_hours_played'] = $total_hours_played;
        $userData['total_calories'] = $total_calories;
        $userData['total_points'] = $total_points;
        $userData['active_tickets'] = $active_tickets;

        return response()->json([
            'success' => true,
            'data' => $userData
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
            'customer_phone' => 'nullable|string|max:20',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->customer_phone = $request->customer_phone;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => $user
        ]);
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8',
            'new_password_confirmation' => 'required|same:new_password'
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Kata sandi saat ini salah.'
            ], 400);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Kata sandi berhasil diubah.'
        ]);
    }
}

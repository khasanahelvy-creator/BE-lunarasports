<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Review;
use App\Models\Booking;
use App\Models\Venue;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'venue_id' => 'required|integer|exists:venues,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
        ]);

        $user = $request->user();
        $booking = Booking::findOrFail($request->booking_id);

        // Ensure the booking belongs to the user
        if ($booking->customer_email !== $user->email) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($booking->is_reviewed) {
            return response()->json(['success' => false, 'message' => 'Booking has already been reviewed'], 400);
        }

        $review = Review::create([
            'user_id' => $user->id,
            'venue_id' => $request->venue_id,
            'booking_id' => $booking->id,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        // Update booking to mark as reviewed
        $booking->update(['is_reviewed' => true]);

        // Recalculate Venue Rating
        $venue = Venue::findOrFail($request->venue_id);
        $averageRating = Review::where('venue_id', $venue->id)->avg('rating');
        $venue->update(['rating' => round($averageRating, 1)]);

        return response()->json([
            'success' => true,
            'message' => 'Terima kasih atas ulasan Anda!',
            'data' => $review
        ]);
    }
}
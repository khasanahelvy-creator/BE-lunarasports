<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Services\FonnteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SendBookingReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-booking-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kirim reminder WhatsApp 15 menit sebelum waktu main';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $now = Carbon::now();
        $targetTime = $now->copy()->addMinutes(15);
        
        // Find bookings for today that are paid
        $bookings = Booking::with(['user', 'court.venue'])
            ->where('status', 'paid')
            ->where('booking_date', $now->toDateString())
            ->get();

        $count = 0;
        foreach ($bookings as $booking) {
            $timeSlots = is_array($booking->time_slots) ? $booking->time_slots : json_decode($booking->time_slots, true);
            if (empty($timeSlots)) continue;

            // Sort times to get the earliest start time
            sort($timeSlots);
            $startTime = trim(explode('-', $timeSlots[0])[0]); // e.g. "08:00" from "08:00 - 09:00"

            $playTime = Carbon::createFromFormat('H:i', $startTime);

            // Check if play time is exactly within the next 15-16 minutes
            // We use a small window so the cron running every minute won't send duplicates
            $diffInMinutes = $now->diffInMinutes($playTime, false);

            if ($diffInMinutes >= 14 && $diffInMinutes <= 16) {
                // Determine user phone
                $phone = $booking->customer_phone ?? $booking->user->customer_phone;
                if ($phone) {
                    $message = "Halo {$booking->user->name}, pengingat jadwal main Anda di {$booking->court->venue->name} (Lap. {$booking->court->name}) akan dimulai dalam 15 menit lagi! ($startTime WITA).\n\nSiap-siap pemanasan ya! 🏃‍♂️🔥";
                    
                    try {
                        FonnteService::sendMessage($phone, $message);
                        $count++;
                        Log::info("Sent reminder to $phone for Booking #{$booking->id}");
                    } catch (\Exception $e) {
                        Log::error("Failed to send reminder to $phone: " . $e->getMessage());
                    }
                }
            }
        }
        
        $this->info("Sent $count booking reminders.");
    }
}
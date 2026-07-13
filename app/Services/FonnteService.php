<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteService
{
    /**
     * Send WhatsApp message via Fonnte
     *
     * @param string $target  The recipient phone number (e.g., '0812...')
     * @param string $message The message body
     * @param string|int $schedule The schedule timestamp (Y-m-d H:i:s) or 0 for instant
     * @return bool
     */
    public function sendMessage($target, $message, $schedule = 0)
    {
        $token = env('FONNTE_TOKEN');
        
        if (empty($token)) {
            Log::warning('Fonnte token is missing. Message not sent.', ['target' => $target]);
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $token,
            ])->post('https://api.fonnte.com/send', [
                'target'   => $target,
                'message'  => $message,
                'schedule' => $schedule,
                'delay'    => '2', // 2 seconds delay to avoid ban
                'countryCode' => '62',
            ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['status']) && $responseData['status'] == true) {
                Log::info('Fonnte message sent/scheduled successfully', [
                    'target' => $target,
                    'schedule' => $schedule,
                ]);
                return true;
            } else {
                Log::error('Fonnte API error', [
                    'target'   => $target,
                    'response' => $responseData
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Fonnte API Exception', [
                'target'  => $target,
                'message' => $e->getMessage()
            ]);
            return false;
        }
    }
}
<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsAppTicket implements ShouldQueue
{
    use Queueable;

    protected $targetPhone;
    protected $waMessage;

    /**
     * Create a new job instance.
     */
    public function __construct($targetPhone, $waMessage)
    {
        $this->targetPhone = $targetPhone;
        $this->waMessage = $waMessage;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30, // Bisa lebih lama karena background job
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => [
                    'target'      => $this->targetPhone,
                    'message'     => $this->waMessage,
                    'countryCode' => '62',
                ],
                CURLOPT_HTTPHEADER => [
                    'Authorization: ' . env('FONNTE_TOKEN'),
                ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($err) {
                Log::error("Fonnte cURL Error: " . $err);
            } else {
                Log::info("Fonnte Response: " . $response);
            }
        } catch (\Exception $e) {
            Log::error("Job SendWhatsAppTicket failed: " . $e->getMessage());
        }
    }
}

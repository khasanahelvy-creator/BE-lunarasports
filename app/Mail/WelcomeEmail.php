<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeEmail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $otp; // 1. Tambahkan variabel OTP di sini

    // 2. Tangkap OTP dari parameter
    public function __construct($user, $otp) 
    {
        $this->user = $user;
        $this->otp = $otp; // 3. Simpan OTP ke variabel public agar bisa dibaca di Blade
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Kode OTP Lunara Sports Anda 🔐', // Ubah judul emailnya biar lebih relevan
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome',
        );
    }
}
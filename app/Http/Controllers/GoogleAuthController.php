<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class GoogleAuthController extends Controller
{
    // Mengarahkan pengguna ke halaman login Google
    public function redirect()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    // Menangkap kembalian dari Google
    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Cari user, kalau belum ada, buatkan akun otomatis
            $user = User::updateOrCreate(
                ['email' => $googleUser->email],
                [
                    'name' => $googleUser->name,
                    'google_id' => $googleUser->id,
                    'password' => bcrypt(Str::random(16)), // Beri password acak karena login via Google
                    'email_verified_at' => now(), // Anggap sudah terverifikasi
                ]
            );

            // Buatkan token akses
            $token = $user->createToken('auth_token')->plainTextToken;

            // Lempar kembali ke React (Sesuaikan port 5173 jika kamu pakai Vite)
            return redirect('http://localhost:5173/google-success?token=' . $token . '&user=' . urlencode(json_encode($user)));

        } catch (\Exception $e) {
            return redirect('http://localhost:5173/login?error=Gagal login dengan Google');
        }
    }
}
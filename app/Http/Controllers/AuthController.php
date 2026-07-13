<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Mail\WelcomeEmail;
use Illuminate\Support\Facades\Mail;
class AuthController extends Controller
{
    // Fungsi untuk Mendaftar (Register)
  // UBAH FUNGSI REGISTER MENJADI SEPERTI INI:
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $otp = rand(1000, 9999); // Generate 4 digit OTP

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => \Illuminate\Support\Facades\Hash::make($request->password),
            'otp' => $otp // Simpan OTP ke database
        ]);

        // Kirim email beserta OTP-nya
        \Illuminate\Support\Facades\Mail::to($user->email)->send(new \App\Mail\WelcomeEmail($user, $otp));

        // JANGAN KASIH TOKEN DULU KARENA BELUM VERIFIKASI
        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil! Silakan cek email untuk OTP.',
            'email' => $user->email // Kirim email balik ke React untuk penanda
        ], 201);
    }

    // TAMBAHKAN FUNGSI BARU INI DI BAWAHNYA:
   public function verifyOtp(Request $request)
    {
        // Validasi input dari React
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        // 🚨 RADAR DEBUGGING: Kita intip apa yang dibandingkan Laravel 🚨
        if (!$user || (string) $user->otp !== (string) $request->otp) {
            return response()->json([
                'success' => false,
                'message' => 'Kode OTP salah.',
                'debug' => [
                    '1_email_dari_react' => $request->email,
                    '2_otp_diketik_di_react' => $request->otp,
                    '3_otp_asli_di_database' => $user ? $user->otp : 'USER TIDAK DITEMUKAN',
                ]
            ], 400); // Sengaja kita gagalkan untuk melihat data ini
        }

        // Jika benar, hapus OTP
        $user->update(['otp' => null]);
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Verifikasi berhasil!',
            'data' => $user,
            'access_token' => $token
        ]);
    }

    // Fungsi untuk Masuk (Login)
   public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        // 1. Cek apakah usernya ada
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Email tidak ditemukan.'], 404);
        }

        // 2. Cek kecocokan password
        if (!\Illuminate\Support\Facades\Hash::check($request->password, $user->password)) {
            
            // 👇 TAMBAHAN BARU: Cek apakah dia akun Google 👇
            if ($user->google_id) {
                return response()->json([
                    'success' => false, 
                    'message' => 'Akun ini terdaftar via Google. Silakan klik tombol "Masuk dengan Google".'
                ], 401);
            }

            return response()->json(['success' => false, 'message' => 'Password salah!'], 401);
        }

        // 3. Jika sukses login manual
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => $user,
            'access_token' => $token
        ]);
    }
}
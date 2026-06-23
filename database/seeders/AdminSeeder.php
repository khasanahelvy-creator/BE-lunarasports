<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Pastikan tidak duplikat
        $admin = User::firstOrNew(['email' => 'admin@lunara.com']);
        $admin->name = 'Admin Utama';
        $admin->password = Hash::make('admin123'); // Password: admin123
        $admin->is_admin = 1;
        $admin->email_verified_at = now();
        $admin->save();
    }
}

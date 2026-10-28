<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil data dari .env
        $email = env('SUPER_ADMIN_EMAIL', 'admin@landak.go.id');
        $password = env('SUPER_ADMIN_PASSWORD', 'hanyadamin9743');
        $name = env('SUPER_ADMIN_NAME', 'Super Admin');

        // Cek apakah user sudah ada, jika belum buat baru
        $user = User::where('email', $email)->first();

        if (!$user) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'user_type' => 'UMUM',
            ]);
            
            $this->command->info('Super Admin berhasil dibuat dari .env!');
        } else {
            $this->command->info('Super Admin sudah ada di database.');
        }
    }
}
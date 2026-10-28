<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\UnitKerja;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Ambil data dari .env
        $email = env('SUPER_ADMIN_EMAIL', 'admin@landak.go.id');
        $password = env('SUPER_ADMIN_PASSWORD', 'adminhasiau773');
        $name = env('SUPER_ADMIN_NAME', 'Super Admin');

        // Cari ID Unit Kerja Diskominfo secara otomatis dari database
        $diskominfo = UnitKerja::where('nama_unit_kerja', 'like', '%KOMUNIKASI DAN INFORMATIKA%')->first();
        $diskominfoId = $diskominfo ? $diskominfo->id : null;

        // Cek apakah user sudah ada
        $user = User::where('email', $email)->first();

        if (!$user) {
            User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'user_type' => 'ASN', // ✅ Sesuai permintaan PM
                'unit_kerja_id' => $diskominfoId, // ✅ Langsung masuk Diskominfo
            ]);
            
            $this->command->info('Super Admin (ASN Diskominfo) berhasil dibuat dari .env!');
        } else {
            $this->command->info('Super Admin sudah ada di database.');
        }
    }
}
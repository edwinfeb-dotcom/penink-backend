<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // Sesuaikan jika pakai Model AppSetting

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        // Contoh data default (Sesuaikan key & value dengan kebutuhan aplikasimu)
        $settings = [
            ['key' => 'app_name', 'value' => 'PENINK'],
            ['key' => 'app_version', 'value' => '1.0.0'],
            ['key' => 'maintenance_mode', 'value' => '0'],
            ['key' => 'contact_email', 'value' => 'diskominfo@landakkab.go.id'],
        ];

        foreach ($settings as $setting) {
            // Gunakan updateOrInsert agar tidak error jika data sudah ada (duplikat)
            DB::table('app_settings')->updateOrInsert(
                ['key' => $setting['key']], // Cari berdasarkan key
                ['value' => $setting['value']] // Update atau insert value
            );
        }

        $this->command->info('Data App Settings berhasil di-seed!');
    }
}
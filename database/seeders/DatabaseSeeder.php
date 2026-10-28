<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
        public function run(): void
    {
        // ✨ Panggil seeder-seeder buatanmu di sini ✨
        $this->call([
            UserSeeder::class,
            AppSettingSeeder::class,
            UnitKerjaSeeder::class,
        ]);
    }
}
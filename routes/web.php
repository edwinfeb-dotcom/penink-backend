<?php

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Api\ShortLinkController;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// 1. RUTE GOOGLE (Tetap di sini agar tidak mengganggu React)
Route::get('/api/auth/google', function () {
     return Socialite::driver('google')->stateless()->redirect();
});
Route::get('/api/auth/google/callback', [AuthController::class, 'googleCallback']);

// 2. RUTE SHORTLINK (WAJIB di atas React Fallback)
Route::get('/{shortCode}', function ($shortCode) {
    return app(ShortLinkController::class)
        ->redirect(request(), $shortCode);
})->where('shortCode', '[a-zA-Z0-9_-]+'); // Hanya huruf, angka, strip, dan underscore

// 3. RUTE UTAMA REACT (Menggantikan view('welcome'))
Route::get('/', function () {
    return file_get_contents(public_path('index.html'));
});

// 4. RUTE FALLBACK REACT (Menangkap semua rute React Router seperti /dashboard)
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '^(?!api|storage).*$'); 
// ^ Kode di atas artinya: "Tangkap semua rute, KECUALI yang berawalan /api atau /storage"
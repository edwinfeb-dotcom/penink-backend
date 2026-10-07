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

Route::get('/', function () {
    return view('welcome');
});

// --- PINDAHKAN RUTE GOOGLE KE SINI (DI ATAS) ---
Route::get('/api/auth/google', function () {
     return Socialite::driver('google')->stateless()->redirect();
});
Route::get('/api/auth/google/callback', [AuthController::class, 'googleCallback']);
// -----------------------------------------------

// Rute penangkap shortCode tetap di bawah
Route::get('/{shortCode}', function ($shortCode) {
    return app(ShortLinkController::class)
        ->redirect(request(), $shortCode);
});
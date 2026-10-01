<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Api\ShortLinkController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\StatisticsApiController;
use App\Http\Controllers\LinkHubController;
use App\Http\Controllers\Api\FeedbackController;



Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user()->load('unitKerja');
});

Route::middleware('throttle:login')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

Route::get('/public/link-hubs/{shortCode}', [LinkHubController::class, 'publicShow']);
Route::get('/unit-kerjas', function () {
    return response()->json([
        'data' => \App\Models\UnitKerja::orderBy('nama_unit_kerja')->get([
            'id',
            'nama_unit_kerja',
        ]),
    ]);
});

Route::middleware('auth:sanctum')->put('/user/unit-kerja', function (Request $request) {

    $request->validate([
        'unit_kerja_id' => 'required|exists:unit_kerjas,id',
    ]);

    $user = $request->user();

    $user->unit_kerja_id = $request->unit_kerja_id;
    $user->save();

    $user->load('unitKerja');

    return response()->json([
        'message' => 'Unit kerja berhasil diperbarui.',
        'user' => $user,
    ]);
});


Route::get('/integration/statistics', [StatisticsApiController::class, 'index'])
    ->middleware('statistics.api.key');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/feedbacks', [FeedbackController::class, 'store']);
    
    Route::get('/link-hubs', [LinkHubController::class, 'index']);
    Route::post('/link-hubs', [LinkHubController::class, 'store']);
    Route::put('/link-hubs/{id}', [LinkHubController::class, 'update']);
    Route::patch('/link-hubs/{id}/status', [LinkHubController::class, 'toggleStatus']);
    Route::delete('/link-hubs/{id}', [LinkHubController::class, 'destroy']);
    Route::post('/link-hubs/{id}/items', [LinkHubController::class, 'addItem']);
    Route::put('/link-hubs/{hubId}/items/{itemId}', [LinkHubController::class, 'updateItem']);
    Route::delete('/link-hubs/{hubId}/items/{itemId}', [LinkHubController::class, 'deleteItem']);
    Route::patch('/link-hubs/{hubId}/items/{itemId}/status', [LinkHubController::class, 'toggleItemStatus']);

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::put('/profile/password', [AuthController::class, 'changePassword']);

    Route::middleware('throttle:shortlink-create')->group(function () {
    Route::post('/short-links', [ShortLinkController::class, 'store']);});
    Route::get('/short-links', [ShortLinkController::class, 'index']);
    Route::get('/short-links/today-clicks', [ShortLinkController::class, 'todayClicks']);
    Route::get('/short-links/month-clicks', [ShortLinkController::class, 'monthClicks']);
    Route::get('/short-links/qr-scans', [ShortLinkController::class, 'qrScans']);
    Route::get('/short-links/stats', [ShortLinkController::class, 'stats']);

    Route::put('/short-links/{id}', [ShortLinkController::class, 'update']);
    Route::patch('/short-links/{id}/status', [ShortLinkController::class, 'toggleStatus']);
    Route::delete('/short-links/{id}', [ShortLinkController::class, 'destroy']);

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/users', [AdminUserController::class, 'index']);
        Route::post('/users', [AdminUserController::class, 'store']);
        Route::put('/users/{user}', [AdminUserController::class, 'update']);
        Route::delete('/users/{user}', [AdminUserController::class, 'destroy']);
        Route::get('/feedbacks', [FeedbackController::class, 'index']);
        Route::delete('/feedbacks/{id}', [FeedbackController::class, 'destroy']);
        Route::get('/unit-kerjas', [AdminUserController::class, 'unitKerjas']);
        Route::get('/statistics', [AdminUserController::class, 'statistics']);
        Route::get('/short-links', [AdminUserController::class, 'shortLinks']);

        Route::put('/short-links/{id}', [AdminUserController::class, 'updateShortLink']);
        Route::patch('/short-links/{id}/status', [AdminUserController::class, 'toggleShortLink']);
        Route::delete('/short-links/{id}', [AdminUserController::class, 'deleteShortLink']);
    });

});

Route::get('/auth/google', function () {
    return Socialite::driver('google')->redirect();
})->middleware('web');

Route::get('/auth/google/callback', [AuthController::class, 'googleCallback'])
    ->middleware('web');
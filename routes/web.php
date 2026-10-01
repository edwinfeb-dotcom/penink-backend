<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ShortLinkController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/{shortCode}', function ($shortCode) {

    return app(ShortLinkController::class)
        ->redirect(request(), $shortCode);

});
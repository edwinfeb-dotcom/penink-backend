<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyStatisticsApiKey
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = (string) $request->header('X-PENINK-API-KEY', '');

        $validApiKey = (string) config('services.penink_statistics.api_key', '');

        if ($apiKey === '' || $validApiKey === '' || !hash_equals($validApiKey, $apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'API Key tidak valid atau tidak ditemukan.',
            ], 401);
        }

        return $next($request);
    }
}
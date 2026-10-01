<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()->role !== 'ADMIN') {
            return response()->json([
                'message' => 'Akses hanya untuk admin.'
            ], 403);
        }

        return $next($request);
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (strtolower($user->role) !== 'super_admin') {
            return response()->json([
                'message' => 'Akses ditolak. Fitur ini hanya untuk Super Admin.',
            ], 403);
        }

        return $next($request);
    }
}
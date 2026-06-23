<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSuperAdmin
{
    /**
     * Memastikan user yang terautentikasi memiliki role 'superadmin'.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user || $user->role !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Area khusus Superadmin.',
            ], 403);
        }

        return $next($request);
    }
}

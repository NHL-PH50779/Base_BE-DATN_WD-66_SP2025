<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('sanctum')->user();
        
        if (!$user || !in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json([
                'message' => 'Không có quyền truy cập'
            ], 403);
        }

        return $next($request);
    }
}
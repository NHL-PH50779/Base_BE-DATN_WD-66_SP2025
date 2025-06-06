<?php
// app/Http/Middleware/AdminMiddleware.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('sanctum')->user();
        
        if (!$user || !$user->isAdmin()) {
            return response()->json([
                'message' => 'Không có quyền truy cập'
            ], 403);
        }

        return $next($request);
    }
}
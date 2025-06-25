<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = auth('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Vui lòng đăng nhập'
            ], 401);
        }

        // Kiểm tra role admin hoặc super_admin
        if ($user->role !== 'admin' && $user->role !== 'super_admin') {
            return response()->json([
                'message' => 'Bạn không có quyền admin'
            ], 403);
        }

        return $next($request);
    }
}
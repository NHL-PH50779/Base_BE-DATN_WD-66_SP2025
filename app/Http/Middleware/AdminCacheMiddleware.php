<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdminCacheMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') && $request->is('admin/*')) {
            $key = 'admin_cache_' . md5($request->fullUrl());
            
            return Cache::remember($key, 60, function () use ($next, $request) {
                return $next($request);
            });
        }
        
        return $next($request);
    }
}
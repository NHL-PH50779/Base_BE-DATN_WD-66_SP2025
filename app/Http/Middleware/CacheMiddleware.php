<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheMiddleware
{
    public function handle(Request $request, Closure $next, $ttl = 300)
    {
        if ($request->isMethod('GET')) {
            $key = 'api_cache_' . md5($request->fullUrl());
            
            return Cache::remember($key, $ttl, function () use ($next, $request) {
                return $next($request);
            });
        }
        
        return $next($request);
    }
}
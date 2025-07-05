<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OptimizeResponse
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Enable GZIP compression
        if (!$response->headers->has('Content-Encoding')) {
            $response->headers->set('Content-Encoding', 'gzip');
        }
        
        // Cache headers for static content
        if ($request->is('api/brands') || $request->is('api/categories')) {
            $response->headers->set('Cache-Control', 'public, max-age=300'); // 5 minutes
        }
        
        return $response;
    }
}
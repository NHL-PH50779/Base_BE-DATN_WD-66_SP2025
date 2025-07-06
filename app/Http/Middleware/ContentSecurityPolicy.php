<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://sandbox.vnpayment.vn https://vnpayment.vn; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "img-src 'self' data: https: blob:; " .
               "font-src 'self' https://fonts.gstatic.com; " .
               "connect-src 'self' https://sandbox.vnpayment.vn https://vnpayment.vn; " .
               "frame-src 'self' https://sandbox.vnpayment.vn https://vnpayment.vn;";
        
        $response->headers->set('Content-Security-Policy', $csp);
        
        return $response;
    }
}
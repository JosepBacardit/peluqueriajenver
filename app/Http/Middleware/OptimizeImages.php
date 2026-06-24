<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class OptimizeImages
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Add image optimization headers
        if ($response->headers->get('content-type') && str_contains($response->headers->get('content-type'), 'image')) {
            $response->header('Cache-Control', 'public, max-age=31536000, immutable');
            $response->header('Content-Security-Policy', "default-src 'self'; img-src 'self' data: https:;");
        }

        return $response;
    }
}

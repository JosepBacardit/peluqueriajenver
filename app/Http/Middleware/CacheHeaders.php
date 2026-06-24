<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CacheHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $path = $request->getPathInfo();

        // Cache static assets (CSS, JS, fonts, images) for 1 year
        if ($this->isStaticAsset($path)) {
            $response->header('Cache-Control', 'public, max-age=31536000, immutable');
            $response->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
            $response->header('Pragma', 'public');
        }
        // Cache HTML pages (24 hours for homepage, 7 days for others)
        elseif ($request->getMethod() === 'GET' && !$request->isJson() && !$this->isApi($path)) {
            $maxAge = $path === '/' ? 86400 : 604800;
            $response->header('Cache-Control', "public, max-age=$maxAge, must-revalidate");
            $response->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + $maxAge));
        }
        // Don't cache API responses
        else {
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, private');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }

        // Additional security headers
        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('X-Frame-Options', 'SAMEORIGIN');
        $response->header('X-XSS-Protection', '1; mode=block');
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }

    private function isApi($path): bool
    {
        return str_starts_with($path, '/api') || str_starts_with($path, '/admin');
    }

    private function isStaticAsset($path): bool
    {
        $staticExtensions = ['css', 'js', 'woff', 'woff2', 'ttf', 'eot', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return in_array(strtolower($extension), $staticExtensions);
    }
}

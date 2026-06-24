<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CacheHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Cache static assets (CSS, JS, fonts, images)
        if ($this->isStaticAsset($request->getPathInfo())) {
            $response->header('Cache-Control', 'public, max-age=31536000, immutable');
            $response->header('Expires', gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));
        }
        // Cache HTML pages (24 hours for homepage, 7 days for others)
        elseif ($request->getMethod() === 'GET' && !$request->isJson()) {
            $maxAge = $request->path() === '/' ? 86400 : 604800;
            $response->header('Cache-Control', "public, max-age=$maxAge, must-revalidate");
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

    private function isStaticAsset($path): bool
    {
        $staticExtensions = ['css', 'js', 'woff', 'woff2', 'ttf', 'eot', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return in_array(strtolower($extension), $staticExtensions);
    }
}

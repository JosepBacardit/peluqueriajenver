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
        // Public HTML pages (2026-10-06, user's decision): no long
        // max-age any more — a schedule or "Reserva online" change must
        // show up without waiting out a stale copy. Instead, an ETag
        // from the rendered body lets the browser revalidate on every
        // visit with a conditional GET, answered 304 (no body) whenever
        // the content has not actually changed; only a 2xx body gets one
        // (never a redirect, where a wrongly-matched If-None-Match would
        // turn a 3xx into a 304 the browser would not follow).
        elseif ($request->getMethod() === 'GET' && ! $request->isJson() && ! $this->isApi($path)) {
            $response->header('Cache-Control', 'no-cache, private');

            if ($response->isSuccessful()) {
                $response->setEtag(md5($response->getContent()));
                // A match turns this into a 304 with the body stripped;
                // Cache-Control, ETag and the security headers below
                // still go out as usual.
                $response->isNotModified($request);
            }
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

    /**
     * Paths never cached: the API, the admin panel, and the booking pages
     * (free times change constantly and their forms carry a CSRF token).
     */
    private function isApi($path): bool
    {
        return str_starts_with($path, '/api')
            || str_starts_with($path, '/admin')
            || $path === '/reservas'
            || str_starts_with($path, '/reservas/')
            || str_starts_with($path, '/cita/');
    }

    private function isStaticAsset($path): bool
    {
        $staticExtensions = ['css', 'js', 'woff', 'woff2', 'ttf', 'eot', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'];
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return in_array(strtolower($extension), $staticExtensions);
    }
}

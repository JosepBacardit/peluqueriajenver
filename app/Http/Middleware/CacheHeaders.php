<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CacheHeaders
{
    public function handle(Request $request, Closure $next)
    {
        // Laravel routes a HEAD request to the same GET route and
        // controller, but Symfony's Response::prepare() — called by
        // Illuminate\Routing\Router right after the controller runs,
        // before this (global, outermost) middleware ever sees the
        // response on the way back out — empties its body for HEAD per
        // RFC 2616 §14.13, long before we could hash it for the ETag
        // (review finding M1). Pretending the request is a GET for the
        // one call to $next() gets the real rendered body to hash, same
        // as the equivalent GET would send; the method is restored
        // immediately after, and the body is stripped back out below,
        // exactly as prepare() would have done for a real HEAD.
        $isHead = $request->isMethod('HEAD');

        if ($isHead) {
            $request->setMethod('GET');
        }

        $response = $next($request);

        if ($isHead) {
            $request->setMethod('HEAD');
        }

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
        // visit with a conditional GET (or HEAD, which Laravel routes
        // the same as GET but keeps as its own $request->getMethod() —
        // review finding M1: without listing it here too, `curl -I`/a
        // HEAD request fell through to the "no-store" branch below,
        // contradicting what NGINX-CACHE-CONFIG.md's own verification
        // command expects to see), answered 304 (no body) whenever the
        // content has not actually changed; only a 2xx body gets one
        // (never a redirect, where a wrongly-matched If-None-Match would
        // turn a 3xx into a 304 the browser would not follow).
        //
        // "private", not "public" (review finding L2): every response
        // here still carries a session cookie (Set-Cookie), so a shared
        // cache (a CDN, an ISP's proxy) must never reuse one visitor's
        // copy for another's, even though the HTML itself does not
        // differ by visitor today. If a CDN is ever put in front of
        // nginx, this would need revisiting deliberately, not by having
        // "private" quietly block it.
        //
        // This whole ETag scheme only saves a real round trip to
        // PHP-FPM when the same visitor's copy keeps hashing to the same
        // value across requests (review finding L1) — true today because
        // none of these pages render @csrf, old() or a session-dependent
        // notice (BookingPagesCachingTest asserts the ETag stays put
        // across repeated requests in the same session); a future public
        // page that adds any of those would still work correctly, just
        // silently stop ever answering 304.
        elseif (in_array($request->getMethod(), ['GET', 'HEAD'], true) && ! $request->isJson() && ! $this->isApi($path)) {
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

        // Pretending HEAD was GET above (so the ETag above is hashed
        // from the real body) means Response::prepare() never got the
        // chance to strip that body for us — do it ourselves now, the
        // same way it would have (RFC 2616 §14.13: no body in a HEAD
        // response, but Content-Length still reflects what the body
        // would have been).
        if ($isHead) {
            $length = $response->headers->get('Content-Length') ?? strlen((string) $response->getContent());
            $response->setContent(null);
            $response->headers->set('Content-Length', $length);
        }

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

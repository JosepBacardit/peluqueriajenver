<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Keeps every /admin response out of search engines via the
 * X-Robots-Tag header, including redirects (e.g. a guest sent to the
 * login screen) that never render the admin layout's own
 * <meta name="robots"> tag. Global and checked by path, the same way
 * CacheHeaders already treats /admin as a special case, so this applies
 * to a response no matter where it is produced in the middleware stack
 * (e.g. the `auth` middleware's redirect, which would otherwise never
 * reach a middleware only attached to the admin route group).
 */
class NoIndexAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (str_starts_with($request->getPathInfo(), '/admin')) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}

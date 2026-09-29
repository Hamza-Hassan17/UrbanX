<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Every admin panel page is dynamic and personalized (notification counts,
 * live badges, per-user data), so it should never be cached by an
 * intermediate layer (CDN, LiteSpeed Cache, reverse proxy) in front of the
 * app. Without this, a deploy can leave admins staring at a stale cached
 * copy of a page indefinitely, with no way to tell from the browser side --
 * that's what happened here: a page-level cache kept serving pre-update HTML
 * for this exact URL even after the source file, Laravel's view cache, and
 * the browser's own cache were all confirmed correct.
 */
class PreventDashboardCaching
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }
}

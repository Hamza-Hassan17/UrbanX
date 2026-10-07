<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Resolves the admin's current workspace into the session (defaulting to
 * their last-used one, else their first allowed one) and blocks access to
 * routes whose name is tagged to a workspace (config/workspaces.php
 * route_prefixes) the user doesn't have. Routes with no tag -- "platform"
 * items like Customers, Finance, Settings -- are unrestricted here and
 * stay gated by their existing Spatie permission check only.
 *
 * Sidebar visibility is a convenience, not security: this middleware is
 * the actual enforcement, so a direct URL hit is blocked the same as a
 * hidden menu item would be.
 */
class EnsureWorkspaceAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        $isSuperAdmin = $user->hasRole('super-admin');
        $allowed = $user->allowedWorkspaces();

        if (empty($allowed) && !$isSuperAdmin) {
            if ($request->routeIs('no-workspace')) {
                return $next($request);
            }
            return redirect()->route('no-workspace');
        }

        if (!session()->has('workspace') || !in_array(session('workspace'), $allowed, true)) {
            $preferred = $user->last_workspace;
            session(['workspace' => ($preferred && in_array($preferred, $allowed, true)) ? $preferred : ($allowed[0] ?? null)]);
        }

        $routeName = $request->route()?->getName();
        $requiredWorkspace = null;

        if ($routeName) {
            foreach (config('workspaces.route_prefixes', []) as $prefix => $workspace) {
                if (str_starts_with($routeName, $prefix)) {
                    $requiredWorkspace = $workspace;
                    break;
                }
            }
        }

        if ($requiredWorkspace && !$isSuperAdmin && !in_array($requiredWorkspace, $allowed, true)) {
            abort(403, 'You do not have access to this workspace.');
        }

        return $next($request);
    }
}

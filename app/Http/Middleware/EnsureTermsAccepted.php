<?php

namespace App\Http\Middleware;

use App\Models\TermsAndCondition;
use Closure;
use Illuminate\Http\Request;

/**
 * Blocks every authenticated API action until the user has accepted the
 * currently published Terms & Conditions -- this is the actual "not
 * allowed to log in" enforcement; the mobile app showing a popup is just
 * UX, this is what makes it real. A short allowlist of paths stays open
 * so the app can still complete OTP verification, fetch/accept the terms
 * themselves, check who it's logged in as, and log out.
 */
class EnsureTermsAccepted
{
    private array $exemptPaths = [
        'api/logout',
        'api/resend-otp',
        'api/otp-verification',
        'api/user',
        'api/terms-and-conditions',
        'api/terms-and-conditions/accept',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (!$user || in_array($request->path(), $this->exemptPaths, true)) {
            return $next($request);
        }

        if (!$user->hasAcceptedCurrentTerms()) {
            $terms = TermsAndCondition::current();

            return response()->json([
                'message' => 'You must accept the latest Terms & Conditions to continue.',
                'terms_accepted' => false,
                'terms' => $terms ? [
                    'id' => $terms->id,
                    'content' => $terms->content,
                ] : null,
            ], 403);
        }

        return $next($request);
    }
}

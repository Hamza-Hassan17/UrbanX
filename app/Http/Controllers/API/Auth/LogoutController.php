<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends Controller
{
    /**
     * Revoke the current token of the logged-in user.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();

            return response()->json([
                'message' => 'Successfully logged out.'
            ], Response::HTTP_OK);
        }

        return response()->json([
            'message' => 'No active session found.'
        ], Response::HTTP_BAD_REQUEST);
    }
}
<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Models\TermsAndCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class TermsController extends Controller
{
    /**
     * Public -- no auth needed, since the app may show this before a user
     * has an account (signup screen) as well as right after login.
     */
    public function current()
    {
        try {
            $terms = TermsAndCondition::current();

            if (!$terms) {
                return response()->json(['message' => 'No terms published yet.'], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'id' => $terms->id,
                'content' => $terms->content,
                'updated_at' => $terms->created_at->toIso8601String(),
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('Terms Current Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function accept(Request $request)
    {
        try {
            $user = $request->user();
            $terms = TermsAndCondition::current();

            if (!$terms) {
                return response()->json(['message' => 'No terms published yet.'], Response::HTTP_NOT_FOUND);
            }

            $user->terms_accepted_version_id = $terms->id;
            $user->terms_accepted_at = now();
            $user->save();

            return response()->json([
                'message' => 'Terms accepted.',
                'terms_accepted' => true,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('Terms Accept Failed', ['error' => $th->getMessage()]);
            return response()->json(['message' => 'Something went wrong!'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

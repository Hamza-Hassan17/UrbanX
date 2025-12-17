<?php

namespace App\Http\Controllers\API\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Complain;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class ComplainController extends Controller
{
    public function getComplains(Request $request)
    {
        try {
            $user = $request->user();
            $complains = Complain::where('user_id', $user->id)->latest()->get();
            return response()->json([
                'complains' => $complains,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Complain Data failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function submitComplain(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'complain_text' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $user = $request->user();

            $complain = new Complain();
            $complain->user_id = $user->id;
            $complain->name = $request->input('name');
            $complain->subject = $request->input('subject');
            $complain->complain_text = $request->input('complain_text');
            $complain->save();

            app('notificationService')->notifyUsers(
                [$user],
                'Complain Submitted',
                'Your complain has been submitted successfully and is pending review.',
                'complains',
                $complain->id,
                'complain_details'
            );


            return response()->json([
                'message' => 'Complain submitted successfully',
                'complain' => [
                    'name' => $complain->name,
                    'subject' => $complain->subject,
                    'complain_text' => $complain->complain_text,
                    'status' => 'pending',
                ]
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Create Complain failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

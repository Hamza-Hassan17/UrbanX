<?php

namespace App\Http\Controllers\API\Frontend\Driver;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportRequest;
use App\Models\User;
use App\Http\Controllers\Dashboard\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Driver-side half of the support-request/messaging feature. A driver opens
 * a request (pending), and cannot send any message until an admin/operator
 * approves it -- see sendMessage()'s status guard. Approval is a shared
 * inbox model: any staff with 'manage support requests' can approve/reply,
 * no per-request assignment.
 */
class SupportController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $driver = $request->user();

            $supportRequest = SupportRequest::create([
                'driver_id' => $driver->id,
                'subject' => $request->subject,
                'status' => 'pending',
            ]);

            try {
                broadcast(new \App\Events\SupportRequestCreated($supportRequest));
            } catch (\Throwable $e) {
                Log::error('SupportRequestCreated broadcast failed', ['error' => $e->getMessage()]);
            }

            $admins = User::role(UserController::ADMIN_PANEL_ROLES)->get();
            app('notificationService')->notifyUsers(
                $admins,
                'New Support Request',
                "{$driver->name} requested support: {$supportRequest->subject}",
                'support_requests',
                $supportRequest->id,
                'support_request_details'
            );

            return response()->json([
                'message' => 'Support request submitted. An admin will review it shortly.',
                'support_request' => $supportRequest,
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            Log::error('API Create Support Request failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function index(Request $request)
    {
        try {
            $requests = SupportRequest::where('driver_id', $request->user()->id)
                ->latest()
                ->get();

            return response()->json([
                'support_requests' => $requests,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API List Support Requests failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function messages(Request $request, $id)
    {
        try {
            $supportRequest = SupportRequest::with('approver:id,name,phone')
                ->where('id', $id)
                ->where('driver_id', $request->user()->id)
                ->first();

            if (!$supportRequest) {
                return response()->json(['message' => 'Support request not found.'], Response::HTTP_NOT_FOUND);
            }

            // Only once approved -- and only if the approving admin actually
            // has a phone number on file -- can the driver call back. Calling
            // is gated the same way messaging already is: pending/rejected/
            // closed requests can't reach anyone.
            $canCall = $supportRequest->status === 'approved'
                && $supportRequest->approver
                && $supportRequest->approver->phone;

            return response()->json([
                'support_request' => $supportRequest,
                'messages' => $supportRequest->messages,
                'can_call' => $canCall,
                'call_number' => $canCall ? $supportRequest->approver->phone : null,
            ], Response::HTTP_OK);
        } catch (\Throwable $th) {
            Log::error('API Get Support Messages failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function sendMessage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $driver = $request->user();

            $supportRequest = SupportRequest::where('id', $id)
                ->where('driver_id', $driver->id)
                ->first();

            if (!$supportRequest) {
                return response()->json(['message' => 'Support request not found.'], Response::HTTP_NOT_FOUND);
            }

            if ($supportRequest->status !== 'approved') {
                return response()->json([
                    'message' => $supportRequest->status === 'pending'
                        ? 'This request is still pending admin approval.'
                        : 'This request is not open for messages.',
                ], Response::HTTP_FORBIDDEN);
            }

            $message = SupportMessage::create([
                'support_request_id' => $supportRequest->id,
                'sender_id' => $driver->id,
                'sender_role' => 'driver',
                'message' => $request->message,
            ]);

            try {
                broadcast(new \App\Events\SupportMessageSent($message));
            } catch (\Throwable $e) {
                Log::error('SupportMessageSent broadcast failed', ['error' => $e->getMessage()]);
            }

            $admins = User::role(UserController::ADMIN_PANEL_ROLES)->get();
            app('notificationService')->notifyUsers(
                $admins,
                'New Support Message',
                "{$driver->name}: {$request->message}",
                'support_requests',
                $supportRequest->id,
                'support_request_details'
            );

            return response()->json([
                'message' => 'Message sent.',
                'data' => $message,
            ], Response::HTTP_CREATED);
        } catch (\Throwable $th) {
            Log::error('API Send Support Message failed', ['error' => $th->getMessage()]);
            return response()->json([
                'message' => 'Something went wrong!'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

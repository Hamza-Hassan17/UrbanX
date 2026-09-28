<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\SupportRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SupportRequestController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view support requests');
        try {
            $status = $request->query('status');

            $requests = SupportRequest::with('driver')
                ->when($status, fn ($q) => $q->where('status', $status))
                ->latest()
                ->get();

            return view('dashboard.support-requests.index', compact('requests', 'status'));
        } catch (\Throwable $th) {
            Log::error('Support Requests Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function show(string $id)
    {
        $this->authorize('view support requests');
        try {
            $supportRequest = SupportRequest::with(['driver', 'messages.sender'])->findOrFail($id);

            return view('dashboard.support-requests.show', compact('supportRequest'));
        } catch (\Throwable $th) {
            Log::error('Support Request Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function approve(string $id)
    {
        $this->authorize('manage support requests');
        try {
            $supportRequest = SupportRequest::findOrFail($id);
            $supportRequest->status = 'approved';
            $supportRequest->approved_by = auth()->id();
            $supportRequest->approved_at = now();
            $supportRequest->save();

            $this->notifyStatusChange($supportRequest, 'Support Request Approved', 'An admin approved your support request. You can now send messages.');

            return redirect()->back()->with('success', 'Support request approved.');
        } catch (\Throwable $th) {
            Log::error('Support Request Approve Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function reject(Request $request, string $id)
    {
        $this->authorize('manage support requests');
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $supportRequest = SupportRequest::findOrFail($id);
            $supportRequest->status = 'rejected';
            $supportRequest->rejection_reason = $request->rejection_reason;
            $supportRequest->save();

            $this->notifyStatusChange($supportRequest, 'Support Request Rejected', "Your support request was rejected: {$request->rejection_reason}");

            return redirect()->back()->with('success', 'Support request rejected.');
        } catch (\Throwable $th) {
            Log::error('Support Request Reject Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function close(string $id)
    {
        $this->authorize('manage support requests');
        try {
            $supportRequest = SupportRequest::findOrFail($id);
            $supportRequest->status = 'closed';
            $supportRequest->save();

            $this->notifyStatusChange($supportRequest, 'Support Request Closed', 'This support request has been closed.');

            return redirect()->back()->with('success', 'Support request closed.');
        } catch (\Throwable $th) {
            Log::error('Support Request Close Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function reply(Request $request, string $id)
    {
        $this->authorize('manage support requests');
        $validator = Validator::make($request->all(), [
            'message' => 'required|string|max:2000',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            $supportRequest = SupportRequest::findOrFail($id);

            if ($supportRequest->status !== 'approved') {
                return redirect()->back()->with('error', 'This request must be approved before replying.');
            }

            $message = SupportMessage::create([
                'support_request_id' => $supportRequest->id,
                'sender_id' => auth()->id(),
                'sender_role' => 'admin',
                'message' => $request->message,
            ]);

            try {
                broadcast(new \App\Events\SupportMessageSent($message));
            } catch (\Throwable $e) {
                Log::error('SupportMessageSent broadcast failed', ['error' => $e->getMessage()]);
            }

            app('notificationService')->notifyUsers(
                [$supportRequest->driver],
                'Support Reply',
                $request->message,
                'support_requests',
                $supportRequest->id,
                'support_request_details'
            );

            return redirect()->back()->with('success', 'Reply sent.');
        } catch (\Throwable $th) {
            Log::error('Support Request Reply Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    private function notifyStatusChange(SupportRequest $supportRequest, string $title, string $message): void
    {
        try {
            broadcast(new \App\Events\SupportRequestStatusUpdated($supportRequest));
        } catch (\Throwable $e) {
            Log::error('SupportRequestStatusUpdated broadcast failed', ['error' => $e->getMessage()]);
        }

        app('notificationService')->notifyUsers(
            [$supportRequest->driver],
            $title,
            $message,
            'support_requests',
            $supportRequest->id,
            'support_request_details'
        );
    }
}

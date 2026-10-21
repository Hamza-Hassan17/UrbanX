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
            'service' => 'nullable|in:ride,rental,food,parcel',
            'ride_id' => 'nullable|integer|prohibits:restaurant_order_id',
            'restaurant_order_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $request->user();

        // Batch 3 Part 1 -- a linked ride/order must be the user's own (as
        // passenger/customer, or the driver/rider who did the job), and
        // decides the service tag instead of the client-sent one.
        $rideId = null;
        $orderId = null;
        $service = $request->input('service');

        if ($request->filled('restaurant_order_id')) {
            $order = \App\Models\RestaurantOrder::find($request->restaurant_order_id);
            $orderRiderId = $order && $order->ride_id ? \App\Models\Ride::whereKey($order->ride_id)->value('driver_id') : null;
            if (!$order || ((int) $order->customer_id !== (int) $user->id && (int) $orderRiderId !== (int) $user->id)) {
                return response()->json([
                    'message' => 'This order was not found on your account.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $orderId = $order->id;
            $rideId = $order->ride_id;
            $service = 'food';
        } elseif ($request->filled('ride_id')) {
            $ride = \App\Models\Ride::find($request->ride_id);
            if (!$ride || ((int) $ride->passenger_id !== (int) $user->id && (int) $ride->driver_id !== (int) $user->id)) {
                return response()->json([
                    'message' => 'This ride was not found on your account.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $rideId = $ride->id;
            if ($ride->ride_type === 'ride') {
                $service = 'ride';
            } else {
                $linkedOrder = \App\Models\RestaurantOrder::where('ride_id', $ride->id)->first();
                $service = $linkedOrder ? 'food' : 'parcel';
                $orderId = $linkedOrder?->id;
            }
        }

        try {
            $complain = new Complain();
            $complain->user_id = $user->id;
            $complain->name = $request->input('name');
            $complain->subject = $request->input('subject');
            $complain->complain_text = $request->input('complain_text');
            $complain->service = $service;
            $complain->ride_id = $rideId;
            $complain->restaurant_order_id = $orderId;
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
                    'id' => $complain->id,
                    'service' => $complain->service,
                    'ride_id' => $complain->ride_id,
                    'restaurant_order_id' => $complain->restaurant_order_id,
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

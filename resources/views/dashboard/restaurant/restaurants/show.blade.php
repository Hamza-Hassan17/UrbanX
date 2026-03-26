@extends('layouts.master')

@section('title', __('Restaurant Details'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.restaurants.index') }}">{{ __('Restaurants') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Show') }}</li>
@endsection

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    <!-- BASIC INFO -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>{{ $restaurant->name }}</h4>
        </div>
        <div class="card-body">

            <div class="row">
                <div class="col-md-3">
                    <img src="{{ asset('storage/'.$restaurant->logo) }}" class="img-fluid rounded mb-2">
                    <img src="{{ asset('storage/'.$restaurant->cover_image) }}" class="img-fluid rounded">
                </div>

                <div class="col-md-9">
                    <p><strong>Category:</strong> {{ $restaurant->category->name ?? '-' }}</p>
                    <p><strong>Owner:</strong> {{ $restaurant->user->name ?? '-' }}</p>
                    <p><strong>Address:</strong> {{ $restaurant->address }}</p>
                    <p><strong>Status:</strong>
                        <span class="badge bg-{{ $restaurant->is_open == 'open' ? 'success' : 'danger' }}">
                            {{ $restaurant->is_open }}
                        </span>
                    </p>
                    <p><strong>Active:</strong> {{ $restaurant->is_active }}</p>
                    <p><strong>Description:</strong> {{ $restaurant->description }}</p>

                    <p><strong>Latitude:</strong> {{ $restaurant->latitude }}</p>
                    <p><strong>Longitude:</strong> {{ $restaurant->longitude }}</p>
                </div>
            </div>

        </div>
    </div>

    <!-- MENUS & ITEMS -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Menus & Items</h5>
        </div>
        <div class="card-body">

            @foreach($restaurant->menus as $menu)
                <div class="mb-3">
                    <h6 class="text-primary">{{ $menu->name }}</h6>
                    <p>{{ $menu->description }}</p>

                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Price</th>
                                <th>Discount</th>
                                <th>Final Price</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($menu->items as $item)
                                <tr>
                                    <td>{{ $item->name }}</td>
                                    <td>{{ $item->price }}</td>
                                    <td>{{ $item->discount_percentage }}%</td>
                                    <td>{{ $item->discount_price ?? $item->price }}</td>
                                    <td>
                                        <span class="badge bg-{{ $item->is_available ? 'success' : 'danger' }}">
                                            {{ $item->is_available ? 'Available' : 'Not Available' }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            @endforeach

        </div>
    </div>

    <!-- ORDERS -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Orders</h5>
        </div>
        <div class="card-body">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Payment</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($restaurant->orders as $order)
                        <tr>
                            <td>{{ $order->order_number }}</td>
                            <td>{{ $order->total_price }}</td>
                            <td>{{ $order->status }}</td>
                            <td>{{ $order->payment_status }}</td>
                            <td>{{ $order->created_at->format('d M Y') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

        </div>
    </div>

    <!-- REVIEWS -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Reviews</h5>
        </div>
        <div class="card-body">

            @foreach($restaurant->reviews as $review)
                <div class="border p-3 mb-2 rounded">
                    <strong>Rating:</strong> ⭐ {{ $review->rating }} <br>
                    <strong>Comment:</strong> {{ $review->comment ?? 'No comment' }} <br>
                    <small>{{ $review->created_at->diffForHumans() }}</small>
                </div>
            @endforeach

        </div>
    </div>

    <!-- SCHEDULE -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Weekly Schedule</h5>
        </div>
        <div class="card-body">
            @php
                $schedule = json_decode($restaurant->weekly_schedule, true);
            @endphp

            @if($schedule)
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Opening</th>
                            <th>Closing</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($schedule as $day => $time)
                            <tr>
                                <td>{{ ucfirst($day) }}</td>
                                <td>{{ $time['open'] ?? '-' }}</td>
                                <td>{{ $time['close'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p>No schedule available</p>
            @endif
        </div>
    </div>

</div>
@endsection

@section('script')
@endsection

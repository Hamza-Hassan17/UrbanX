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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4>{{ $restaurant->name }}</h4>

                <button class="btn btn-primary btn-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#editRestaurantModal">
                    <i class="ti ti-edit"></i> Edit
                </button>
            </div>
            <div class="card-body">

                <div class="row">
                    <div class="col-md-3">
                        <img src="{{ asset('storage/' . $restaurant->logo) }}" class="img-fluid rounded mb-2">
                        <img src="{{ asset('storage/' . $restaurant->cover_image) }}" class="img-fluid rounded">
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

                @foreach ($restaurant->menus as $menu)
                    <div class="mb-3">
                        <h6 class="text-primary d-flex justify-content-between">
                            {{ $menu->name }}

                            <button class="btn btn-sm btn-outline-primary editMenuBtn"
                                data-bs-toggle="modal"
                                data-bs-target="#editMenuModal{{$menu->id}}">
                                <i class="ti ti-edit"></i>
                            </button>
                        </h6>
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
                                @foreach ($menu->items as $item)
                                    <tr>
                                        <td>
                                            {{ $item->name }}

                                            <button class="btn btn-sm btn-outline-primary editItemBtn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editItemModal{{ $item->id }}">
                                                <i class="ti ti-edit"></i>
                                            </button>
                                        </td>
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
                        @foreach ($restaurant->orders as $order)
                            <tr>
                                <td>{{ $order->order_number }}</td>
                                <td>{{ $order->total_price }}</td>
                                <td>
                                    {{ $order->status }}

                                    <button class="btn btn-sm btn-outline-primary editOrderBtn"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editOrderModal{{ $order->id }}">
                                        <i class="ti ti-edit"></i>
                                    </button>
                                </td>
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

                @foreach ($restaurant->reviews as $review)
                    <div class="border p-3 mb-2 rounded">
                        <strong>Rating:</strong> ⭐ {{ $review->rating }}

                        <button class="btn btn-sm btn-outline-primary float-end editReviewBtn"
                            data-bs-toggle="modal"
                            data-bs-target="#editReviewModal{{ $review->id }}">
                            <i class="ti ti-edit"></i>
                        </button> <br>
                        <strong>Comment:</strong> {{ $review->comment ?? 'No comment' }} <br>
                        <small>{{ $review->created_at->diffForHumans() }}</small>
                    </div>
                @endforeach

            </div>
        </div>

        <!-- SCHEDULE -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <h5>Weekly Schedule</h5>

                <button class="btn btn-sm btn-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#editScheduleModal"
                    data-schedule="{{ $restaurant->weekly_schedule }}">
                    <i class="ti ti-edit"></i> Edit
                </button>
            </div>
            <div class="card-body">
                @php
                    $scheduleRaw = $restaurant->weekly_schedule;

                    // Step 1: First decode
                    $schedule = json_decode($scheduleRaw, true);

                    // Step 2: Double encoded check
                    if (is_string($schedule)) {
                        $schedule = json_decode($schedule, true);
                    }
                @endphp

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>

                        {{-- CASE 1: Proper JSON array --}}
                        @if(is_array($schedule) && count($schedule))
                            @foreach($schedule as $day => $time)
                                <tr>
                                    <td>{{ ucfirst($day) }}</td>
                                    <td>{{ $time ?? '-' }}</td>
                                </tr>
                            @endforeach

                        {{-- CASE 2: Direct string like "12:00 AM - 12:00 PM" --}}
                        @elseif(is_string($scheduleRaw) && !empty($scheduleRaw))
                            <tr>
                                <td>All Days</td>
                                <td>{{ $scheduleRaw }}</td>
                            </tr>

                        {{-- CASE 3: null --}}
                        @else
                            <tr>
                                <td colspan="2" class="text-center">No schedule available</td>
                            </tr>
                        @endif

                    </tbody>
                </table>
            </div>
        </div>

    </div>

    {{-- Modals --}}
    <div class="modal fade" id="editRestaurantModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('dashboard.restaurants.update', $restaurant->id) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Edit Restaurant</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        <div class="mb-2">
                            <label>Name</label>
                            <input type="text" name="name" class="form-control" value="{{ $restaurant->name }}">
                        </div>

                        <div class="mb-2">
                            <label>Category</label>
                            <select name="restaurant_category_id" class="form-control select2">
                                @if (isset($categories) && count($categories) > 0)
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ $restaurant->restaurant_category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>

                        <div class="mb-2">
                            <label>Address</label>
                            <input type="text" name="address" class="form-control" value="{{ $restaurant->address }}">
                        </div>

                        <div class="mb-2">
                            <label>Description</label>
                            <textarea name="description" class="form-control">{{ $restaurant->description }}</textarea>
                        </div>

                        <div class="mb-2">
                            <label>Logo</label>
                            <input class="form-control" type="file"
                                id="logo" name="logo" accept="image/*"/>
                        </div>

                        <div class="mb-2">
                            <label>Cover Image</label>
                            <input class="form-control" type="file"
                                id="cover_image" name="cover_image" accept="image/*"/>
                        </div>

                        <div class="mb-2">
                            <label>Status</label>
                            <select name="is_open" class="form-control select2">
                                <option value="open" {{ $restaurant->is_open == 'open' ? 'selected' : '' }}>Open</option>
                                <option value="closed" {{ $restaurant->is_open == 'closed' ? 'selected' : '' }}>Closed</option>
                            </select>
                        </div>

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @foreach ($restaurant->menus as $menu)
        <div class="modal fade" id="editMenuModal{{ $menu->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('dashboard.restaurants.menus.update', $menu->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>Edit Menu</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <div class="mb-2">
                                <label>Name</label>
                                <input type="text" name="name" class="form-control" value="{{ $menu->name }}">
                            </div>

                            <div class="mb-2">
                                <label>Description</label>
                                <textarea name="description" class="form-control">{{ $menu->description }}</textarea>
                            </div>

                            <div class="mb-2">
                                <label>Status</label>
                                <select name="is_active" class="form-control select2">
                                    <option value="active" {{ $restaurant->is_active == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ $restaurant->is_active == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    @foreach ($restaurant->menus as $menu)
        @foreach ($menu->items as $item)
            <div class="modal fade" id="editItemModal{{ $item->id }}" tabindex="-1">
                <div class="modal-dialog">
                    <form method="POST" action="{{ route('dashboard.restaurants.items.update', $item->id) }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="modal-content">
                            <div class="modal-header">
                                <h5>Edit Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">

                                <div class="mb-2">
                                    <label>Name</label>
                                    <input type="text" name="name" class="form-control" value="{{ $item->name }}">
                                </div>

                                <div class="mb-2">
                                    <label>Menu</label>
                                    <select name="restaurant_menu_id" class="form-control select2">
                                        @if (isset($restaurant->menus) && count($restaurant->menus) > 0)
                                            @foreach ($restaurant->menus as $men)
                                                <option value="{{ $men->id }}" {{ $item->restaurant_menu_id == $men->id ? 'selected' : '' }}>{{ $men->name }}</option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label>Description</label>
                                    <textarea name="description" class="form-control">{{ $item->description }}</textarea>
                                </div>

                                <div class="mb-2">
                                    <label>Price</label>
                                    <input type="integer" step="any" name="price" class="form-control" value="{{ $item->price }}">
                                </div>

                                <div class="mb-2">
                                    <label>Discount %</label>
                                    <input type="integer" name="discount_percentage" class="form-control" value="{{ $item->discount_percentage }}">
                                </div>

                                <div class="mb-2">
                                    <label>Preparation Time (minutes)</label>
                                    <input type="integer" step="any" name="preparation_time" class="form-control" value="{{ $item->preparation_time }}">
                                </div>

                                <div class="mb-2">
                                    <label>Image</label>
                                    <input class="form-control" type="file" id="image" name="image" accept="image/*"/>
                                </div>

                                <div class="mb-2">
                                    <label>Available?</label>
                                    <select name="is_available" class="form-control select2">
                                        <option value="1" {{ $item->is_available == '1' ? 'selected' : '' }}>Available</option>
                                        <option value="0" {{ $item->is_available == '0' ? 'selected' : '' }}>Not Available</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label>Featured?</label>
                                    <select name="is_featured" class="form-control select2">
                                        <option value="1" {{ $item->is_featured == '1' ? 'selected' : '' }}>Featured</option>
                                        <option value="0" {{ $item->is_featured == '0' ? 'selected' : '' }}>Not Featured</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label>Status</label>
                                    <select name="is_active" class="form-control select2">
                                        <option value="active" {{ $restaurant->is_active == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ $restaurant->is_active == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    </select>
                                </div>

                            </div>

                            <div class="modal-footer">
                                <button class="btn btn-primary">Update</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    @endforeach

    @foreach ($restaurant->orders as $order)
        <div class="modal fade" id="editOrderModal{{ $order->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('dashboard.restaurants.orders.update', $order->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>Edit Order</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">

                            <div class="mb-2">
                                <label>Status</label>
                                <select name="status" class="form-control select2">
                                    <option value="pending" {{ $order->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="preparing" {{ $order->status == 'preparing' ? 'selected' : '' }}>Preparing</option>
                                    <option value="delivered" {{ $order->status == 'delivered' ? 'selected' : '' }}>Delivered</option>
                                    <option value="completed" {{ $order->status == 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" {{ $order->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label>Payment Status</label>
                                <select name="payment_status" class="form-control select2">
                                    <option value="unpaid" {{ $order->payment_status == 'unpaid' ? 'selected' : '' }}>Unpaid</option>
                                    <option value="paid" {{ $order->payment_status == 'paid' ? 'selected' : '' }}>Paid</option>
                                    <option value="failed" {{ $order->payment_status == 'failed' ? 'selected' : '' }}>Failed</option>
                                </select>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach

    <div class="modal fade" id="editScheduleModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" action="{{ route('dashboard.restaurants.schedule.update', $restaurant->id) }}">
                @csrf
                @method('PUT')

                @php
                    $schedule = json_decode($restaurant->weekly_schedule, true);
                    if (is_string($schedule)) {
                        $schedule = json_decode($schedule, true);
                    }
                @endphp

                <div class="modal-content">
                    <div class="modal-header">
                        <h5>Edit Schedule</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">

                        @php
                            $days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
                        @endphp

                        @foreach($days as $day)
                            @php
                                $time = $schedule[$day] ?? '';
                                $parts = explode('-', $time);
                                $start = trim($parts[0] ?? '');
                                $end = trim($parts[1] ?? '');
                            @endphp

                            <div class="row mb-2">
                                <div class="col-md-4">
                                    <label class="text-capitalize">{{ $day }}</label>
                                </div>

                                <div class="col-md-4">
                                    <input type="text" name="start[{{ $day }}]" class="form-control"
                                        placeholder="Start (10:00 AM)" value="{{ $start }}">
                                </div>

                                <div class="col-md-4">
                                    <input type="text" name="end[{{ $day }}]" class="form-control"
                                        placeholder="End (11:00 PM)" value="{{ $end }}">
                                </div>
                            </div>
                        @endforeach

                    </div>

                    <div class="modal-footer">
                        <button class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @foreach ($restaurant->reviews as $review)
        <div class="modal fade" id="editReviewModal{{ $review->id }}" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('dashboard.restaurants.reviews.update', $review->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="modal-content">
                        <div class="modal-header">
                            <h5>Edit Review</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">

                            <div class="mb-2">
                                <label>Rating</label>
                                <input type="number" name="rating" class="form-control" value="{{ $review->rating }}">
                            </div>

                            <div class="mb-2">
                                <label>Comment</label>
                                <textarea name="comment" class="form-control">{{ $review->comment }}</textarea>
                            </div>

                            <div class="mb-2">
                                <label>Status</label>
                                <select name="is_active" class="form-control select2">
                                    <option value="active" {{ $review->is_active == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ $review->is_active == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>

                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-primary">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endforeach


@endsection

@section('script')
@endsection

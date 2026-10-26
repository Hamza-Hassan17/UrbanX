@extends('layouts.master')

@section('title', __('Delivery Orders'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Delivery') }}</li>
@endsection

@section('css')
    @include('dashboard.partials.queue-styles')
@endsection

@section('content')
    <div class="dashboard-container">
        @include('dashboard.partials.queue-table', [
            'queueTitle' => 'Orders Queue',
            'itemPrefix' => 'ORDER-',
            'rides' => $rides,
            'queuePresets' => $queuePresets,
            'queueTypeChips' => ['food' => '🍔 Food', 'parcel' => '📦 Parcel'],
            'queueEditable' => false,
        ])
    </div>
@endsection

@section('script')
    <script>
        // View button -> ride detail page (incl. Replay Route of the rider's GPS path).
        const deliveryRideUrl = @json(route('dashboard.rides.show', ['ride' => '__ID__']));
        window.renderQueueActions = function (ride) {
            return `<a href="${deliveryRideUrl.replace('__ID__', ride.id)}" class="queue-edit-btn" title="View details and replay route"><i class="fas fa-eye"></i> View</a>`;
        };
    </script>
    @include('dashboard.partials.queue-scripts', [
        'queueUrl' => route('dashboard.delivery.queue'),
        'queuePresetUrl' => route('dashboard.delivery.queue.presets'),
        'queuePresets' => $queuePresets,
        'lastQueueFilters' => $lastQueueFilters,
        'itemPrefix' => 'ORDER-',
        'queueTitle' => 'Orders',
        'hasTypeChips' => true,
        'queueEditable' => false,
    ])
@endsection

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
    @include('dashboard.partials.queue-scripts', [
        'queueUrl' => route('dashboard.delivery.queue'),
        'queuePresetUrl' => route('dashboard.delivery.queue.presets'),
        'queuePresets' => $queuePresets,
        'lastQueueFilters' => $lastQueueFilters,
        'itemPrefix' => 'ORDER-',
        'queueTitle' => 'Orders',
        'hasTypeChips' => true,
    ])
@endsection

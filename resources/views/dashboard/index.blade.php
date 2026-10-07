@extends('layouts.master')

@section('title', 'Dashboard')

@section('css')
<style>
    .stats-card {
        transition: all 0.3s ease;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    canvas {
        max-height: 300px;
    }
</style>
@endsection

@section('content')
@if (isset($ridesKpis))
    @include('dashboard.partials.rides-dashboard')
@elseif (isset($deliveryKpis))
    @include('dashboard.partials.delivery-dashboard')
@else
    @include('dashboard.partials.platform-dashboard')
@endif
@endsection

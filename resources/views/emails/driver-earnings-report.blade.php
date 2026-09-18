@extends('layouts.mails.master')

@section('title', 'Your Earnings Report')

@section('css')
@endsection

@section('content')
    <p>{{ __('Hi') }} <strong>{{ $driver->name }}</strong>,</p>
    <p>{{ __('Here is your earnings summary for :start to :end.', ['start' => $startDate, 'end' => $endDate]) }}</p>

    <div class="credentials">
        <ul>
            <p><strong>{{ __('Total Rides:') }}</strong> {{ $row['total_rides'] }}</p>
            <p><strong>{{ __('Total Earnings:') }}</strong> {{ \App\Helpers\Helper::formatCurrency($row['total_earnings']) }}</p>
        </ul>
    </div>

    <p>{{ __('The full breakdown is attached as a PDF.') }}</p>
@endsection

@section('script')
@endsection

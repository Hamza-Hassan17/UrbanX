@extends('layouts.master')

@section('title', __('Manual Delivery Assignment'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Manual Delivery Assignment') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">{{ __('Unassigned Deliveries') }}</h5>
                    <small class="text-muted">{{ __('Food orders accepted by the restaurant and parcel jobs still waiting for a rider.') }}</small>
                </div>
                <span class="badge bg-primary rounded-pill">{{ $riders->count() }} {{ __('riders available') }}</span>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('ID') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Time') }}</th>
                            <th>{{ __('Pickup') }}</th>
                            <th>{{ __('Dropoff') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Fare') }}</th>
                            <th style="min-width: 260px">{{ __('Assign Rider') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td><a href="{{ route('dashboard.rides.show', $row['id']) }}">DEL-{{ $row['id'] }}</a></td>
                                <td>
                                    <span class="badge {{ $row['type_label'] === 'Food' ? 'bg-label-warning' : 'bg-label-info' }}">{{ $row['type_label'] }}</span>
                                    @if ($row['restaurant'])
                                        <div class="small text-muted">{{ $row['restaurant'] }}</div>
                                    @endif
                                </td>
                                <td>{{ $row['time'] }}</td>
                                <td class="small">{{ $row['pickup'] }}</td>
                                <td class="small">{{ $row['dropoff'] }}</td>
                                <td>
                                    {{ $row['customer'] ?? '--' }}
                                    <div class="small text-muted">{{ $row['phone'] }}</div>
                                </td>
                                <td>{{ \App\Helpers\Helper::formatCurrency($row['fare']) }}</td>
                                <td>
                                    <form method="POST" action="{{ route('dashboard.rides.update', $row['id']) }}" class="d-flex gap-2">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="ride_id" value="{{ $row['id'] }}">
                                        <input type="hidden" name="status" value="accepted">
                                        <select name="driver_id" class="form-select form-select-sm" required>
                                            <option value="">{{ __('Select rider') }}</option>
                                            @foreach ($riders as $rider)
                                                <option value="{{ $rider->id }}">
                                                    {{ $rider->name }}{{ $rider->profile?->city ? ' -- ' . $rider->profile->city : '' }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary" onclick="return confirm('{{ __('Assign this delivery to the selected rider?') }}')">{{ __('Assign') }}</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted">{{ __('No unassigned deliveries right now') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.master')

@section('title', __($title))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __($title) }}</li>
@endsection

@section('content')
    @php
        $query = ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')];
        $sortUrl = function ($column) use ($query, $sort, $dir, $indexRoute) {
            $nextDir = ($sort === $column && $dir === 'desc') ? 'asc' : 'desc';
            return route($indexRoute, array_merge($query, ['sort' => $column, 'dir' => $nextDir]));
        };
    @endphp
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="{{ route($indexRoute) }}" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">{{ __('From') }}</label>
                        <input type="date" name="from" class="form-control" value="{{ $from->format('Y-m-d') }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">{{ __('To') }}</label>
                        <input type="date" name="to" class="form-control" value="{{ $to->format('Y-m-d') }}">
                    </div>
                    <input type="hidden" name="sort" value="{{ $sort }}">
                    <input type="hidden" name="dir" value="{{ $dir }}">
                    <div class="col-md-2">
                        <button class="btn btn-primary w-100">{{ __('Apply') }}</button>
                    </div>
                    <div class="col-md-2">
                        <a href="{{ route($exportRoute, array_merge($query, ['sort' => $sort, 'dir' => $dir])) }}" class="btn btn-outline-secondary w-100">
                            <i class="ti ti-download"></i> {{ __('Export CSV') }}
                        </a>
                    </div>
                </form>
                <small class="text-muted d-block mt-2">
                    {{ __('Acceptance rate = accepted ÷ (accepted + rejected + expired). Pickup time = accepted → started; trip/delivery time = started → completed. Online hours only for drivers using the online toggle (blank = older app).') }}
                </small>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __($title) }}</h5>
                <small class="text-muted">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · {{ $rows->count() }} {{ Str::plural(strtolower($personLabel), $rows->count()) }}</small>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            @foreach ($columns as $key => $label)
                                <th class="text-nowrap">
                                    <a href="{{ $sortUrl($key) }}" class="text-body">
                                        {{ $key === 'name' ? __($personLabel) : __($label) }}
                                        @if ($sort === $key)<i class="ti ti-chevron-{{ $dir === 'asc' ? 'up' : 'down' }}"></i>@endif
                                    </a>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="text-nowrap">
                                    {{ $row['name'] }}
                                    <span class="badge bg-label-{{ $row['presence'] === 'offline' ? 'secondary' : ($row['presence'] === 'busy' ? 'warning' : 'success') }} ms-1">{{ ucfirst($row['presence']) }}</span>
                                    <div class="small text-muted">#{{ $row['driver_id'] }}{{ $row['city'] ? ' · ' . $row['city'] : '' }}</div>
                                </td>
                                <td>{{ $row['offers_received'] }}</td>
                                <td>{{ $row['offers_accepted'] }}</td>
                                <td>{{ $row['offers_rejected'] }}</td>
                                <td>{{ $row['offers_expired'] }}</td>
                                <td>{{ $row['acceptance_rate'] !== null ? $row['acceptance_rate'] . '%' : '—' }}</td>
                                <td class="{{ $row['cancellations'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $row['cancellations'] }}</td>
                                <td>{{ $row['completed_jobs'] }}</td>
                                <td>{{ $row['avg_rating'] !== null ? $row['avg_rating'] . ' (' . $row['reviews'] . ')' : '—' }}</td>
                                <td>{{ $row['avg_pickup_minutes'] ?? '—' }}</td>
                                <td>{{ $row['avg_trip_minutes'] ?? '—' }}</td>
                                <td>{{ $row['online_hours'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}" class="text-center text-muted">{{ __('No data for this period') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

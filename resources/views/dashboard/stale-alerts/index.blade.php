@extends('layouts.master')

@section('title', __('Alerts'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Alerts') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">{{ __('Stale Job Alerts') }} -- {{ ucfirst($workspace) }}</h5>
                <a href="{{ request()->fullUrlWithQuery(['show_resolved' => request()->boolean('show_resolved') ? 0 : 1]) }}" class="btn btn-sm btn-outline-secondary">
                    {{ request()->boolean('show_resolved') ? __('Hide Resolved') : __('Show Resolved') }}
                </a>
            </div>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>{{ __('Raised') }}</th>
                            <th>{{ __('Type') }}</th>
                            <th>{{ __('Message') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Resolved By') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alerts as $alert)
                            <tr>
                                <td class="text-nowrap">{{ $alert->created_at->format('d M, Y h:i A') }}</td>
                                <td>{{ ucwords(str_replace('_', ' ', $alert->type)) }}</td>
                                <td>
                                    {{ $alert->message }}
                                    @if ($alert->subject_url)
                                        <a href="{{ $alert->subject_url }}" class="d-block">{{ __('View Job') }} &rarr;</a>
                                    @endif
                                </td>
                                <td>
                                    @if ($alert->status === 'open')
                                        <span class="badge bg-label-danger">{{ __('Open') }}</span>
                                    @else
                                        <span class="badge bg-label-success">{{ __('Resolved') }}</span>
                                    @endif
                                </td>
                                <td>{{ $alert->resolvedBy->name ?? '--' }}</td>
                                <td>
                                    @can(['resolve stale alerts'])
                                        @if ($alert->status === 'open')
                                            <button type="button" class="btn btn-sm btn-success resolve-alert-btn"
                                                data-bs-toggle="modal" data-bs-target="#resolveAlertModal"
                                                data-alert-id="{{ $alert->id }}">
                                                {{ __('Resolve') }}
                                            </button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">{{ __('No alerts found.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-body">
                {{ $alerts->links() }}
            </div>
        </div>

        <div class="modal fade" id="resolveAlertModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ url('dashboard/alerts') }}/__ID__/resolve">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ __('Resolve Alert') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label class="form-label">{{ __('Note') }}</label>
                                <textarea class="form-control" name="note" rows="3" placeholder="{{ __('Optional') }}"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                            <button type="submit" class="btn btn-success">{{ __('Resolve') }}</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.resolve-alert-btn');
            if (!btn) return;
            const form = document.querySelector('#resolveAlertModal form');
            form.action = form.action.replace('__ID__', btn.dataset.alertId);
        });
    </script>
@endsection

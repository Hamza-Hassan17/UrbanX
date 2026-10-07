@extends('layouts.master')

@section('title', __('Terms & Conditions'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Terms & Conditions') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-4">
            <div class="card-body d-flex gap-4 flex-wrap">
                <div>
                    <small class="text-muted d-block">{{ __('Current Version') }}</small>
                    <h4 class="mb-0">{{ $current ? '#' . $current->id : __('None published yet') }}</h4>
                </div>
                @if ($current)
                    <div>
                        <small class="text-muted d-block">{{ __('Published') }}</small>
                        <h4 class="mb-0">{{ $current->created_at->format('d M Y h:i A') }}</h4>
                    </div>
                    <div>
                        <small class="text-muted d-block">{{ __('Accepted By') }}</small>
                        <h4 class="mb-0">{{ $acceptedCount }} / {{ $totalUsers }} {{ __('users') }}</h4>
                    </div>
                @endif
            </div>
        </div>

        @can(['update terms'])
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">{{ __('Publish New Version') }}</h5>
                    <small class="text-muted">{{ __('Publishing creates a new version. Anyone who already accepted an older version will be prompted to accept this one before they can continue using the app.') }}</small>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('dashboard.terms.store') }}">
                        @csrf
                        <div class="mb-4">
                            <label for="content" class="form-label">{{ __('Content') }}</label><span class="text-danger">*</span>
                            <textarea class="form-control @error('content') is-invalid @enderror" id="content" name="content"
                                rows="14" placeholder="{{ __('Enter the full Terms & Conditions text (HTML allowed)') }}" required>{{ old('content', $current->content ?? '') }}</textarea>
                            @error('content')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <button type="submit" class="btn btn-primary"
                            onclick="return confirm('{{ __('This publishes a new version and will re-prompt every user who already accepted the current one. Continue?') }}');">
                            {{ __('Publish New Version') }}
                        </button>
                    </form>
                </div>
            </div>
        @endcan

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">{{ __('Version History') }}</h5>
            </div>
            <div class="table-responsive">
                <table class="table border-top">
                    <thead>
                        <tr>
                            <th>{{ __('Version') }}</th>
                            <th>{{ __('Published By') }}</th>
                            <th>{{ __('Published At') }}</th>
                            <th>{{ __('Preview') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($history as $version)
                            <tr>
                                <td>
                                    #{{ $version->id }}
                                    @if ($current && $version->id === $current->id)
                                        <span class="badge bg-label-success ms-1">{{ __('Current') }}</span>
                                    @endif
                                </td>
                                <td>{{ $version->creator->name ?? '—' }}</td>
                                <td>{{ $version->created_at->format('d M Y h:i A') }}</td>
                                <td class="text-muted" style="max-width:400px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ strip_tags($version->content) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center text-muted">{{ __('No versions published yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

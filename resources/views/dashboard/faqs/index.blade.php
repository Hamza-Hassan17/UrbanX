@extends('layouts.master')

@section('title', __('Help FAQs'))

@section('css')
@endsection

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Help FAQs') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        @can(['manage support requests'])
        <div class="card mb-6">
            <h5 class="card-header">{{ $editing ? __('Edit Question') : __('Add Question') }}</h5>
            <div class="card-body pt-4">
                <form method="POST" action="{{ $editing ? route('dashboard.faqs.update', $editing) : route('dashboard.faqs.store') }}">
                    @csrf
                    @if ($editing)
                        @method('PUT')
                    @endif
                    <div class="row">
                        <div class="mb-4 col-md-4">
                            <label for="audience" class="form-label">{{ __('Shown in') }} <span class="text-danger">*</span></label>
                            <select class="form-select @error('audience') is-invalid @enderror" id="audience" name="audience">
                                @foreach ($audiences as $value => $label)
                                    <option value="{{ $value }}" @selected(old('audience', $editing->audience ?? 'restaurant') === $value)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            @error('audience')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="question" class="form-label">{{ __('Question') }} <span class="text-danger">*</span></label>
                            <input class="form-control @error('question') is-invalid @enderror" type="text" id="question" name="question" maxlength="255"
                                value="{{ old('question', $editing->question ?? '') }}" placeholder="{{ __('e.g. When do I get paid?') }}" />
                            @error('question')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-2">
                            <label for="sort_order" class="form-label">{{ __('Position') }}</label>
                            <input class="form-control" type="number" min="0" id="sort_order" name="sort_order" value="{{ old('sort_order', $editing->sort_order ?? 0) }}" />
                        </div>
                        <div class="mb-4 col-12">
                            <label for="answer" class="form-label">{{ __('Answer') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('answer') is-invalid @enderror" id="answer" name="answer" rows="4">{{ old('answer', $editing->answer ?? '') }}</textarea>
                            @error('answer')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ $editing ? __('Save Changes') : __('Add Question') }}</button>
                    @if ($editing)
                        <a href="{{ route('dashboard.faqs.index') }}" class="btn btn-label-secondary ms-2">{{ __('Cancel') }}</a>
                    @endif
                </form>
            </div>
        </div>
        @endcan

        <div class="card">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-3">
                <h5 class="mb-0">{{ __('Help FAQs') }}</h5>
                <form method="GET" action="{{ route('dashboard.faqs.index') }}">
                    <select class="form-select form-select-sm" name="audience" onchange="this.form.submit()" aria-label="{{ __('Filter by app') }}">
                        <option value="">{{ __('All apps') }}</option>
                        @foreach ($audiences as $value => $label)
                            <option value="{{ $value }}" @selected(request('audience') === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table border-top">
                    <thead>
                        <tr>
                            <th>{{ __('Question') }}</th>
                            <th>{{ __('Shown in') }}</th>
                            <th>{{ __('Position') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($faqs as $faq)
                            <tr>
                                <td style="max-width: 420px;">
                                    <div class="fw-medium">{{ $faq->question }}</div>
                                    <div class="small text-muted text-truncate">{{ \Illuminate\Support\Str::limit($faq->answer, 120) }}</div>
                                </td>
                                <td>{{ __($audiences[$faq->audience] ?? $faq->audience) }}</td>
                                <td>{{ $faq->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $faq->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ $faq->is_active ? __('Shown') : __('Hidden') }}
                                    </span>
                                </td>
                                <td>
                                    @can(['manage support requests'])
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('dashboard.faqs.index', ['edit' => $faq->id]) }}" class="btn btn-sm btn-outline-primary">{{ __('Edit') }}</a>
                                            <form method="POST" action="{{ route('dashboard.faqs.toggle', $faq) }}">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $faq->is_active ? __('Hide') : __('Show') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('dashboard.faqs.destroy', $faq) }}" onsubmit="return confirm('{{ __('Delete this question?') }}')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">{{ __('No questions yet. Add the first one above.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

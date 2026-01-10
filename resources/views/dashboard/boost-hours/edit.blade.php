@extends('layouts.master')

@section('title', __('Edit Boost Hour'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.boost-hours.index') }}">{{ __('Boost Hours') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Edit') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <!-- Account -->
            <div class="card-body pt-4">
                <form method="POST" action="{{ route('dashboard.boost-hours.update', $boostHour->id) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <div class="row p-5">
                        <h3>{{ __('Edit Boost Hour') }}</h3>
                        <div class="mb-4 col-md-4">
                            <label for="start" class="form-label">{{ __('Start Hour') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('start') is-invalid @enderror" type="time" id="start"
                                name="start" required placeholder="{{ __('Enter start hours') }}" autofocus
                                value="{{ old('start', $boostHour->start) }}" />
                            @error('start')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="end" class="form-label">{{ __('End Hour') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('end') is-invalid @enderror" type="time" id="end"
                                name="end" required placeholder="{{ __('Enter end hours') }}" autofocus
                                value="{{ old('end', $boostHour->end) }}" />
                            @error('end')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>

                        <div class="mb-4 col-md-4">
                            <label for="multiplier" class="form-label">{{ __('Multiplier (i.e. 1.5)') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('multiplier') is-invalid @enderror" type="number"
                                step="any" id="multiplier" name="multiplier" value="{{ old('multiplier', $boostHour->multiplier) }}" required
                                placeholder="{{ __('Enter multiplier') }}" />
                            @error('multiplier')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary me-3">{{ __('Edit Boost Hour') }}</button>
                    </div>
                </form>
            </div>
            <!-- /Account -->
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).ready(function() {
            //
        });
    </script>
@endsection

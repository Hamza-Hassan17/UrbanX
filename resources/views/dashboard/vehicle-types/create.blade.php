@extends('layouts.master')

@section('title', __('Create Vehicle Type'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.vehicle-types.index') }}">{{ __('Vehicle Types') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <!-- Account -->
            <div class="card-body pt-4">
                <form method="POST" action="{{ route('dashboard.vehicle-types.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row p-5">
                        <h3>{{ __('Add New Vehicle Type') }}</h3>
                        <div class="mb-4 col-md-4">
                            <label for="name" class="form-label">{{ __('Name') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('name') is-invalid @enderror" type="text" id="name"
                                name="name" required placeholder="{{ __('Enter name') }}" autofocus
                                value="{{ old('name') }}" />
                            @error('name')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="base_fare" class="form-label">{{ __('Base Fare per KM') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('base_fare') is-invalid @enderror" type="number"
                                min="0" id="base_fare" name="base_fare" value="{{ old('base_fare') }}" required
                                placeholder="{{ __('Enter base fare per km') }}" />
                            @error('base_fare')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="first_km_price" class="form-label">{{ __('First KM Price') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('first_km_price') is-invalid @enderror" type="number"
                                min="0" id="first_km_price" name="first_km_price" value="{{ old('first_km_price') }}"
                                required placeholder="{{ __('Enter first km price') }}" />
                            @error('first_km_price')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="other_km_price" class="form-label">{{ __('Other KM Price') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('other_km_price') is-invalid @enderror" type="number"
                                min="0" id="other_km_price" name="other_km_price" value="{{ old('other_km_price') }}"
                                required placeholder="{{ __('Enter other km price') }}" />
                            @error('other_km_price')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="seats" class="form-label">{{ __('Seats') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('seats') is-invalid @enderror" type="number" min="0"
                                id="seats" name="seats" value="{{ old('seats') }}" required
                                placeholder="{{ __('Enter no of seats') }}" />
                            @error('seats')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-12">
                            <label for="code" class="form-label">{{ __('Icon') }}</label><span
                                class="text-danger">*</span>
                            <input type="hidden" name="icon" id="icon">

                            <div class="d-flex flex-wrap gap-2">
                                @php
                                    $icons = [
                                        'ev-car.svg',
                                        'limousine.svg',
                                        'luxury-car.svg',
                                        'motorcycle.svg',
                                        'taxi-4.svg',
                                        'taxi-7.svg',
                                    ];
                                @endphp

                                @foreach ($icons as $ic)
                                    <div class="icon-option border rounded p-2 text-center" data-name="{{ $ic }}"
                                        style="cursor:pointer; width:80px;">
                                        <img src="{{ asset('icons/' . $ic) }}" class="img-fluid"
                                            alt="{{ $ic }}">
                                        {{-- <small>{{ pathinfo($ic, PATHINFO_FILENAME) }}</small> --}}
                                    </div>
                                @endforeach
                            </div>

                            @error('icon')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary me-3">{{ __('Add Vehicle Type') }}</button>
                    </div>
                </form>
            </div>
            <!-- /Account -->
        </div>
    </div>
@endsection

@section('script')
    <script>
        $(document).on('click', '.icon-option', function() {
            $('.icon-option').removeClass('border-primary');
            $(this).addClass('border-primary');
            $('#icon').val($(this).data('name'));
        });
    </script>
@endsection

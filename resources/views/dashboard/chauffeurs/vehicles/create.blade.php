@extends('layouts.master')

@section('title', __('Create Chauffeurs Vehicle'))

@section('css')
@endsection


@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.chauffeur-vehicles.index') }}">{{ __('Chauffeurs Vehicles') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Create') }}</li>
@endsection
@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <!-- Account -->
            <div class="card-body pt-4">
                <form method="POST" action="{{ route('dashboard.chauffeur-vehicles.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row p-5">
                        <h3>{{ __('Add New Vehicle') }}</h3>
                        <div class="mb-4 col-md-6">
                            <label class="form-label" for="car_brand_id">{{ __('Brand/Make') }}<span
                                class="text-danger">*</span></label>
                            <select id="car_brand_id" name="car_brand_id" class="select2 form-select @error('car_brand_id') is-invalid @enderror" required>
                                <option value="" selected disabled>{{ __('Select Car Brand') }}</option>
                                @if (isset($carBrands) && count($carBrands) > 0)
                                    @foreach ($carBrands as $brand)
                                        <option value="{{ $brand->id }}"
                                            {{ $brand->id == old('car_brand_id') ? 'selected' : '' }}>{{ $brand->name }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            @error('car_brand_id')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="title" class="form-label">{{ __('Title') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('title') is-invalid @enderror" type="text" id="title"
                                name="title" required placeholder="{{ __('Enter title') }}"
                                value="{{ old('title') }}" />
                            @error('title')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="car_model" class="form-label">{{ __('Car Model') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('car_model') is-invalid @enderror" type="text" id="car_model" name="car_model" value="{{ old('car_model') }}" required
                                placeholder="{{ __('Enter car model') }}" />
                            @error('car_model')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="car_fuel_type" class="form-label">{{ __('Car Fuel Type') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('car_fuel_type') is-invalid @enderror" type="text"
                                id="car_fuel_type" name="car_fuel_type" value="{{ old('car_fuel_type') }}" required
                                placeholder="{{ __('Enter car fuel type') }}" />
                            @error('car_fuel_type')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="year" class="form-label">{{ __('Year') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('year') is-invalid @enderror" type="text"
                                id="year" name="year" value="{{ old('year') }}" required
                                placeholder="{{ __('YYYY') }}" />
                            @error('year')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="price_per_hour" class="form-label">{{ __('Price Per Hour') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('price_per_hour') is-invalid @enderror" type="number" min="0" step="0.01"
                                id="price_per_hour" name="price_per_hour" value="{{ old('price_per_hour') }}" required
                                placeholder="{{ __('i.e. 100') }}" />
                            @error('price_per_hour')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="price_per_day" class="form-label">{{ __('Price Per Day') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('price_per_day') is-invalid @enderror" type="number" min="0" step="0.01"
                                id="price_per_day" name="price_per_day" value="{{ old('price_per_day') }}" required
                                placeholder="{{ __('i.e. 1000') }}" />
                            @error('price_per_day')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="price_per_week" class="form-label">{{ __('Price Per Week') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('price_per_week') is-invalid @enderror" type="number" min="0" step="0.01"
                                id="price_per_week" name="price_per_week" value="{{ old('price_per_week') }}" required
                                placeholder="{{ __('i.e. 10000') }}" />
                            @error('price_per_week')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="price_per_month" class="form-label">{{ __('Price Per Hour') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('price_per_month') is-invalid @enderror" type="number" min="0" step="0.01"
                                id="price_per_month" name="price_per_month" value="{{ old('price_per_month') }}" required
                                placeholder="{{ __('i.e. 1000000') }}" />
                            @error('price_per_month')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="transmission" class="form-label">{{ __('Transmission') }}<span
                                class="text-danger">*</span></label>
                            <select id="transmission" name="transmission" class="select2 form-select @error('transmission') is-invalid @enderror" required>
                                <option value="" selected disabled>{{ __('Select Transmission') }}</option>
                                <option value="automatic"{{ old('transmission') == 'automatic' ? 'selected' : '' }}>{{__('Automatic')}}</option>
                                <option value="manual"{{ old('transmission') == 'manual' ? 'selected' : '' }}>{{__('Manual')}}</option>
                                <option value="semi-automatic"{{ old('transmission') == 'semi-automatic' ? 'selected' : '' }}>{{__('Semi Automatic')}}</option>
                                <option value="cvt"{{ old('transmission') == 'cvt' ? 'selected' : '' }}>{{__('CVT')}}</option>
                            </select>
                            @error('transmission')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="color" class="form-label">{{ __('Color') }}<span
                                class="text-danger">*</span></label>
                            <input class="form-control @error('color') is-invalid @enderror" type="text" id="color"
                                name="color" placeholder="{{ __('Enter color') }}"
                                value="{{ old('color') }}" required/>
                            @error('color')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="seats" class="form-label">{{ __('Seats') }}<span
                                class="text-danger">*</span></label>
                            <input class="form-control @error('seats') is-invalid @enderror" type="number" id="seats"
                                name="seats" placeholder="{{ __('Enter no of seats') }}"
                                value="{{ old('seats') }}" required/>
                            @error('seats')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-12">
                            <label for="main_image" class="form-label">{{ __('Main Image') }}<span
                                class="text-danger">*</span></label>
                            <input class="form-control @error('main_image') is-invalid @enderror" type="file"
                                id="main_image" name="main_image" accept="image/*" required/>
                            @error('main_image')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-12">
                            <label for="address" class="form-label">{{ __('Address') }}</label><span
                                class="text-danger">*</span>
                            <input class="form-control @error('address') is-invalid @enderror" type="text" id="address"
                                name="address" placeholder="{{ __('Enter address') }}"
                                value="{{ old('address') }}" />
                            @error('address')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-12">
                            <label for="description" class="form-label">{{ __('Description') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                placeholder="{{ __('Enter description') }}" cols="30" rows="10">{{ old('description') }}</textarea>
                            @error('description')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="switch switch-square">
                                <label for="is_featured" class="switch-label">{{ __('Featured') }} <br> <small>Is this brand is a featuring brand?</small></label>
                                <input type="checkbox" class="switch-input @error('is_featured') is-invalid @enderror" id="is_featured"
                                    name="is_featured" />
                                <span class="switch-toggle-slider">
                                    <span class="switch-on"></span>
                                    <span class="switch-off"></span>
                                </span>
                            </label>
                            @error('is_featured')
                                <span class="invalid-feedback" role="alert">
                                    <strong>{{ $message }}</strong>
                                </span>
                            @enderror
                        </div>
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-primary me-3">{{ __('Add Vehicle') }}</button>
                    </div>
                </form>
            </div>
            <!-- /Account -->
        </div>
    </div>
@endsection

@section('script')
@endsection

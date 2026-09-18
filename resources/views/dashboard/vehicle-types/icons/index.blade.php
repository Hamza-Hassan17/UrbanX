@extends('layouts.master')

@section('title', __('Vehicle Type Icons'))

@section('css')
@endsection

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.vehicle-types.index') }}">{{ __('Vehicle Types') }}</a></li>
    <li class="breadcrumb-item active">{{ __('Icons') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <h5 class="card-header">{{ __('Upload New Icon') }}</h5>
            <div class="card-body">
                <form action="{{ route('dashboard.vehicle-type-icons.store') }}" method="POST" enctype="multipart/form-data" class="d-flex align-items-start gap-2">
                    @csrf
                    <div>
                        <input type="file" name="icon" class="form-control @error('icon') is-invalid @enderror" accept=".svg,.png,.jpg,.jpeg" required>
                        @error('icon')
                            <span class="invalid-feedback d-block" role="alert">
                                <strong>{{ $message }}</strong>
                            </span>
                        @enderror
                    </div>
                    <button type="submit" class="btn btn-primary text-nowrap">{{ __('Upload') }}</button>
                </form>
            </div>
        </div>

        <div class="card">
            <h5 class="card-header">{{ __('Icon Pool') }}</h5>
            <div class="card-body">
                @if ($icons->isEmpty())
                    <p class="text-muted mb-0">{{ __('No icons uploaded yet.') }}</p>
                @else
                    <div class="d-flex flex-wrap gap-3">
                        @foreach ($icons as $icon)
                            <div class="border rounded p-2 text-center" style="width:110px;">
                                <img src="{{ asset($icon->path) }}" class="img-fluid mb-2" alt="{{ $icon->name }}" style="height:50px;">
                                <div class="text-truncate small mb-2" title="{{ $icon->name }}">{{ $icon->name }}</div>
                                <form action="{{ route('dashboard.vehicle-type-icons.destroy', $icon->id) }}" method="POST">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                        class="btn btn-icon btn-text-danger waves-effect waves-light rounded-pill delete_confirmation"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Delete Icon') }}">
                                        <i class="ti ti-trash ti-md"></i>
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

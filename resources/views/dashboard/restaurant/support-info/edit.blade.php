@extends('layouts.master')

@section('title', __('Contact & About'))

@section('css')
@endsection

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Contact & About') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-6">
            <h5 class="card-header">{{ __('Urban Eats: Contact & About') }}</h5>
            <div class="card-body pt-4">
                <form method="POST" action="{{ route('dashboard.eats-support-info.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="row">
                        @foreach ([
                            'phone' => [__('Phone'), 'text'],
                            'email' => [__('Email'), 'email'],
                            'address' => [__('Address'), 'text'],
                            'facebook_url' => [__('Facebook link'), 'url'],
                            'instagram_url' => [__('Instagram link'), 'url'],
                            'twitter_url' => [__('Twitter link'), 'url'],
                        ] as $field => [$label, $type])
                            <div class="mb-4 col-md-6">
                                <label for="{{ $field }}" class="form-label">{{ $label }}</label>
                                <input class="form-control @error($field) is-invalid @enderror" type="{{ $type }}"
                                    id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $info->$field) }}" />
                                @error($field)
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        @endforeach
                        <div class="mb-4 col-12">
                            <label for="about" class="form-label">{{ __('About') }}</label>
                            <textarea class="form-control @error('about') is-invalid @enderror" id="about" name="about" rows="6">{{ old('about', $info->about) }}</textarea>
                            @error('about')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                    </div>
                    @can(['update restaurant category'])
                        <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                    @endcan
                </form>
            </div>
        </div>
    </div>
@endsection

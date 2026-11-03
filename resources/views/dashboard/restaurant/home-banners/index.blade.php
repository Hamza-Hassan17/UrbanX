@extends('layouts.master')

@section('title', __('Home Banners'))

@section('css')
@endsection

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __('Home Banners') }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        @can(['update restaurant category'])
        <div class="card mb-6">
            <h5 class="card-header">{{ __('Add Banner') }}</h5>
            <div class="card-body pt-4">
                <form method="POST" action="{{ route('dashboard.home-banners.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="mb-4 col-md-6">
                            <label for="title" class="form-label">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input class="form-control @error('title') is-invalid @enderror" type="text" id="title" name="title" value="{{ old('title') }}" />
                            @error('title')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-6">
                            <label for="image" class="form-label">{{ __('Image (JPG/PNG, 1200x600)') }} <span class="text-danger">*</span></label>
                            <input class="form-control @error('image') is-invalid @enderror" type="file" id="image" name="image" accept="image/*" />
                            @error('image')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="link_type" class="form-label">{{ __('When tapped, open') }}</label>
                            <select class="form-select" id="link_type" name="link_type">
                                <option value="none">{{ __('Nothing') }}</option>
                                <option value="restaurant" @selected(old('link_type') === 'restaurant')>{{ __('A restaurant') }}</option>
                                <option value="category" @selected(old('link_type') === 'category')>{{ __('A category') }}</option>
                            </select>
                        </div>
                        <div class="mb-4 col-md-4" id="restaurant-wrap" style="display:none;">
                            <label for="restaurant_id" class="form-label">{{ __('Restaurant') }}</label>
                            <select class="form-select @error('restaurant_id') is-invalid @enderror" id="restaurant_id" name="restaurant_id">
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($restaurants as $restaurant)
                                    <option value="{{ $restaurant->id }}">{{ $restaurant->name }}</option>
                                @endforeach
                            </select>
                            @error('restaurant_id')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4" id="category-wrap" style="display:none;">
                            <label for="category_id" class="form-label">{{ __('Category') }}</label>
                            <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id">
                                <option value="">{{ __('Select') }}</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('category_id')
                                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                            @enderror
                        </div>
                        <div class="mb-4 col-md-4">
                            <label for="sort_order" class="form-label">{{ __('Position (lowest shows first)') }}</label>
                            <input class="form-control" type="number" min="0" id="sort_order" name="sort_order" value="{{ old('sort_order', 0) }}" />
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('Add Banner') }}</button>
                </form>
            </div>
        </div>
        @endcan

        <div class="card">
            <h5 class="card-header">{{ __('Home Banners') }}</h5>
            <div class="table-responsive">
                <table class="table border-top">
                    <thead>
                        <tr>
                            <th>{{ __('Image') }}</th>
                            <th>{{ __('Title') }}</th>
                            <th>{{ __('Opens') }}</th>
                            <th>{{ __('Position') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($banners as $banner)
                            <tr>
                                <td><img src="{{ asset($banner->image) }}" alt="" width="120" height="60" style="object-fit:cover; border-radius:6px;"></td>
                                <td>{{ $banner->title }}</td>
                                <td>{{ ucfirst($banner->link_type) }}</td>
                                <td>{{ $banner->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $banner->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
                                        {{ $banner->is_active ? __('Active') : __('Inactive') }}
                                    </span>
                                </td>
                                <td class="d-flex gap-2">
                                    @can(['update restaurant category'])
                                        <form method="POST" action="{{ route('dashboard.home-banners.toggle', $banner) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $banner->is_active ? __('Turn off') : __('Turn on') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('dashboard.home-banners.destroy', $banner) }}" onsubmit="return confirm('{{ __('Delete this banner?') }}')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('Delete') }}</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">{{ __('No banners yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function () {
            const type = document.getElementById('link_type');
            if (!type) return;
            const sync = () => {
                document.getElementById('restaurant-wrap').style.display = type.value === 'restaurant' ? '' : 'none';
                document.getElementById('category-wrap').style.display = type.value === 'category' ? '' : 'none';
            };
            type.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection

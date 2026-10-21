@extends('layouts.master')

@section('title', __('Item Add-ons'))

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.restaurants.index') }}">{{ __('Restaurants') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('dashboard.restaurants.show', $item->restaurant_id) }}">{{ $item->restaurant->name ?? __('Restaurant') }}</a></li>
    <li class="breadcrumb-item active">{{ $item->name }}</li>
@endsection

@section('content')
    @php $canEdit = auth()->user()->can('update restaurant'); @endphp
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="mb-1">{{ $item->name }}</h4>
                    <small class="text-muted">
                        {{ __('Base price') }}: {{ \App\Helpers\Helper::formatCurrency($item->basePrice()) }}
                        · {{ $item->optionGroups->count() }} {{ __('option group(s)') }}
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-label-{{ (string) $item->is_available === '1' ? 'success' : 'danger' }}">
                        {{ (string) $item->is_available === '1' ? __('Available') : __('Out of stock') }}
                    </span>
                    @if ($canEdit)
                        <form method="POST" action="{{ route('dashboard.restaurants.items.toggle', $item->id) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">
                                {{ (string) $item->is_available === '1' ? __('Mark out of stock') : __('Mark available') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        @forelse ($item->optionGroups as $group)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-0">{{ $group->name }}</h5>
                        <small class="text-muted">
                            {{ $group->is_required ? __('Required') : __('Optional') }}
                            · {{ $group->selection_type === 'single' ? __('Choose one') : __('Choose multiple') }}
                            @if ($group->selection_type === 'multiple')
                                · {{ __('min') }} {{ $group->effectiveMin() }}, {{ __('max') }} {{ $group->effectiveMax() ?? __('no limit') }}
                            @endif
                            · {{ __('sort') }} {{ $group->sort_order }}
                        </small>
                    </div>
                    @if ($canEdit)
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#editGroup{{ $group->id }}">
                                <i class="ti ti-edit"></i> {{ __('Edit group') }}
                            </button>
                            <form method="POST" action="{{ route('dashboard.restaurants.option-groups.destroy', $group->id) }}"
                                onsubmit="return confirm('{{ __('Delete this option group and its options? Past orders are not affected.') }}')">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                            </form>
                        </div>
                    @endif
                </div>

                @if ($canEdit)
                    <div class="collapse" id="editGroup{{ $group->id }}">
                        <div class="card-body border-bottom bg-light">
                            @include('dashboard.restaurant.restaurants.partials.option-group-form', [
                                'action' => route('dashboard.restaurants.option-groups.update', $group->id),
                                'method' => 'PUT',
                                'group' => $group,
                                'submitLabel' => __('Save group'),
                            ])
                        </div>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>{{ __('Option') }}</th>
                                <th>{{ __('Price adjustment') }}</th>
                                <th>{{ __('Sort') }}</th>
                                <th>{{ __('Status') }}</th>
                                @if ($canEdit)<th style="min-width: 360px">{{ __('Actions') }}</th>@endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($group->options as $option)
                                <tr>
                                    <td>{{ $option->name }}</td>
                                    <td>{{ (float) $option->price_adjustment >= 0 ? '+' : '' }}{{ \App\Helpers\Helper::formatCurrency($option->price_adjustment) }}</td>
                                    <td>{{ $option->sort_order }}</td>
                                    <td>
                                        <span class="badge bg-label-{{ $option->is_available ? 'success' : 'danger' }}">
                                            {{ $option->is_available ? __('Available') : __('Out of stock') }}
                                        </span>
                                    </td>
                                    @if ($canEdit)
                                        <td>
                                            <form method="POST" action="{{ route('dashboard.restaurants.options.update', $option->id) }}" class="d-flex gap-1 align-items-center flex-wrap">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" value="{{ $option->name }}" class="form-control form-control-sm" style="width: 120px" required>
                                                <input type="number" step="0.01" name="price_adjustment" value="{{ $option->price_adjustment }}" class="form-control form-control-sm" style="width: 90px">
                                                <input type="number" name="sort_order" value="{{ $option->sort_order }}" class="form-control form-control-sm" style="width: 60px">
                                                <input type="hidden" name="is_available" value="{{ $option->is_available ? 1 : 0 }}">
                                                <button class="btn btn-sm btn-primary">{{ __('Save') }}</button>
                                            </form>
                                            <div class="d-flex gap-1 mt-1">
                                                <form method="POST" action="{{ route('dashboard.restaurants.options.toggle', $option->id) }}">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-secondary">{{ $option->is_available ? __('Out of stock') : __('Available') }}</button>
                                                </form>
                                                <form method="POST" action="{{ route('dashboard.restaurants.options.destroy', $option->id) }}" onsubmit="return confirm('{{ __('Delete this option?') }}')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger"><i class="ti ti-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted">{{ __('No options yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($canEdit)
                    <div class="card-body border-top">
                        <form method="POST" action="{{ route('dashboard.restaurants.options.store', $group->id) }}" class="row g-2 align-items-end">
                            @csrf
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('New option') }}</label>
                                <input type="text" name="name" class="form-control form-control-sm" placeholder="{{ __('e.g. Extra cheese') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('Price adjustment (Rs)') }}</label>
                                <input type="number" step="0.01" name="price_adjustment" class="form-control form-control-sm" value="0">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small">{{ __('Sort') }}</label>
                                <input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $group->options->count() }}">
                            </div>
                            <input type="hidden" name="is_available" value="1">
                            <div class="col-md-3">
                                <button class="btn btn-sm btn-primary w-100">{{ __('Add option') }}</button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        @empty
            <div class="card mb-4">
                <div class="card-body text-center text-muted">{{ __('This item has no add-ons. Customers order it at the base price.') }}</div>
            </div>
        @endforelse

        @if ($canEdit)
            <div class="card">
                <div class="card-header"><h5 class="mb-0">{{ __('Add option group') }}</h5></div>
                <div class="card-body">
                    @include('dashboard.restaurant.restaurants.partials.option-group-form', [
                        'action' => route('dashboard.restaurants.option-groups.store', $item->id),
                        'method' => 'POST',
                        'group' => null,
                        'submitLabel' => __('Add group'),
                    ])
                </div>
            </div>
        @endif
    </div>
@endsection

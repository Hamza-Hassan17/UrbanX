<form method="POST" action="{{ $action }}" class="row g-2 align-items-end">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    <div class="col-md-3">
        <label class="form-label small">{{ __('Group name') }}</label>
        <input type="text" name="name" class="form-control form-control-sm" value="{{ $group->name ?? '' }}" placeholder="{{ __('e.g. Size, Toppings') }}" required>
    </div>
    <div class="col-md-2">
        <label class="form-label small">{{ __('Required?') }}</label>
        <select name="is_required" class="form-select form-select-sm">
            <option value="0" {{ $group && !$group->is_required ? 'selected' : '' }}>{{ __('Optional') }}</option>
            <option value="1" {{ $group && $group->is_required ? 'selected' : '' }}>{{ __('Required') }}</option>
        </select>
    </div>
    <div class="col-md-2">
        <label class="form-label small">{{ __('Choice') }}</label>
        <select name="selection_type" class="form-select form-select-sm">
            <option value="single" {{ $group && $group->selection_type === 'single' ? 'selected' : '' }}>{{ __('Single') }}</option>
            <option value="multiple" {{ $group && $group->selection_type === 'multiple' ? 'selected' : '' }}>{{ __('Multiple') }}</option>
        </select>
    </div>
    <div class="col-md-1">
        <label class="form-label small" title="{{ __('Multiple choice only') }}">{{ __('Min') }}</label>
        <input type="number" name="min_select" min="0" class="form-control form-control-sm" value="{{ $group->min_select ?? 0 }}">
    </div>
    <div class="col-md-1">
        <label class="form-label small" title="{{ __('Multiple choice only; empty = no limit') }}">{{ __('Max') }}</label>
        <input type="number" name="max_select" min="1" class="form-control form-control-sm" value="{{ $group && $group->selection_type === 'multiple' ? $group->max_select : '' }}">
    </div>
    <div class="col-md-1">
        <label class="form-label small">{{ __('Sort') }}</label>
        <input type="number" name="sort_order" class="form-control form-control-sm" value="{{ $group->sort_order ?? 0 }}">
    </div>
    <div class="col-md-2">
        <button class="btn btn-sm btn-primary w-100">{{ $submitLabel }}</button>
    </div>
    <div class="col-12">
        <small class="text-muted">{{ __('Single choice: customer picks exactly one (if required) or at most one. Multiple: min/max limit the picks; a required group needs at least one.') }}</small>
    </div>
</form>

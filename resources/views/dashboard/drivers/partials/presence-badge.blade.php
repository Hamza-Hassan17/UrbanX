{{-- Batch 3 Part 4 -- online / busy / offline. "Legacy" = never used the
     online toggle (older app build); treated as online for offers. --}}
@php $presence = $driver->presenceStatus(); @endphp
<span class="badge bg-label-{{ $presence === 'offline' ? 'secondary' : ($presence === 'busy' ? 'warning' : 'success') }}"
    title="{{ $driver->online_status === null ? __('Has not used the online toggle (older app) -- treated as online') : '' }}{{ $driver->last_location_at ? ' ' . __('Last location') . ': ' . $driver->last_location_at->diffForHumans() : '' }}">
    {{ ucfirst($presence) }}@if ($driver->online_status === null && $presence !== 'busy') <small>({{ __('legacy') }})</small>@endif
</span>

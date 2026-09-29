{{-- Expects: $path (storage-relative path), $label (alt text), optional $width (default 100) --}}
<div class="d-inline-block text-center me-2 mb-2">
    <img src="{{ asset('storage/' . $path) }}" alt="{{ $label }}" class="rounded border d-block" width="{{ $width ?? 100 }}">
    <a href="{{ asset('storage/' . $path) }}" download class="d-block small mt-1">
        <i class="ti ti-download"></i> Download
    </a>
</div>

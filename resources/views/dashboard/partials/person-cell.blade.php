<div class="d-flex align-items-center">
    <div class="avatar avatar-sm me-3 flex-shrink-0">
        <span class="avatar-initial rounded-circle bg-label-primary">{{ strtoupper(mb_substr($name ?? '?', 0, 1)) }}</span>
    </div>
    <div class="d-flex flex-column">
        @if (!empty($url))
            <a href="{{ $url }}" class="text-heading fw-medium text-nowrap">{{ $name }}</a>
        @else
            <span class="text-heading fw-medium text-nowrap">{{ $name }}</span>
        @endif
        <small class="text-muted text-nowrap">{{ $email }}</small>
    </div>
</div>

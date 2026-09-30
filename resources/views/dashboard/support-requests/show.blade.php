@extends('layouts.master')

@section('title', __('Support Request'))

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.support-requests.index') }}">{{ __('Support Requests') }}</a></li>
    <li class="breadcrumb-item active">#{{ $supportRequest->id }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="card mb-4">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="mb-1">{{ $supportRequest->subject }}</h5>
                    <small class="text-muted">
                        {{ __('From') }} {{ $supportRequest->driver->name ?? 'N/A' }}
                        @if ($supportRequest->driver && $supportRequest->driver->phone)
                            &middot; {{ $supportRequest->driver->phone }}
                        @endif
                        &middot; {{ $supportRequest->created_at->format('M d, Y h:i A') }}
                    </small>
                </div>
                <div class="d-flex gap-2">
                    @php
                        $badgeClass = [
                            'pending' => 'bg-label-warning',
                            'approved' => 'bg-label-success',
                            'rejected' => 'bg-label-danger',
                            'closed' => 'bg-label-secondary',
                        ][$supportRequest->status] ?? 'bg-label-secondary';
                    @endphp
                    <span id="status-badge" class="badge {{ $badgeClass }} align-self-center">{{ ucfirst($supportRequest->status) }}</span>

                    @can(['manage support requests'])
                        {{-- Admin can always call the driver regardless of approval status --
                             the approval gate exists to protect driver-initiated contact
                             (messaging/calling back), not admin's own outreach. --}}
                        @if ($supportRequest->driver && $supportRequest->driver->phone)
                            <a href="tel:{{ $supportRequest->driver->phone }}" class="btn btn-success btn-sm align-self-center">
                                <i class="ti ti-phone"></i> {{ __('Call Driver') }}
                            </a>
                        @endif
                        @if ($supportRequest->status === 'pending')
                            <form action="{{ route('dashboard.support-requests.approve', $supportRequest->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-success btn-sm">{{ __('Approve') }}</button>
                            </form>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">{{ __('Reject') }}</button>
                        @elseif ($supportRequest->status === 'approved')
                            <form action="{{ route('dashboard.support-requests.close', $supportRequest->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-label-secondary btn-sm">{{ __('Close') }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>

            @if ($supportRequest->status === 'rejected' && $supportRequest->rejection_reason)
                <div class="card-body pt-0">
                    <div class="alert alert-danger mb-0">{{ __('Rejection reason:') }} {{ $supportRequest->rejection_reason }}</div>
                </div>
            @endif
        </div>

        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">{{ __('Conversation') }}</h6>
            </div>
            <div class="card-body">
                <div id="messages-list" style="max-height: 420px; overflow-y: auto;">
                    @forelse ($supportRequest->messages as $message)
                        <div class="mb-3 d-flex {{ $message->sender_role === 'admin' ? 'justify-content-end' : 'justify-content-start' }}">
                            <div class="p-3 rounded {{ $message->sender_role === 'admin' ? 'bg-label-primary' : 'bg-label-secondary' }}" style="max-width: 70%;">
                                <div class="small fw-bold mb-1">{{ $message->sender->name ?? ucfirst($message->sender_role) }}</div>
                                <div>{{ $message->message }}</div>
                                <div class="small text-muted mt-1">{{ $message->created_at->format('M d, h:i A') }}</div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted text-center">{{ __('No messages yet.') }}</p>
                    @endforelse
                </div>

                @can(['manage support requests'])
                    @if ($supportRequest->status === 'approved')
                        <form id="replyForm" action="{{ route('dashboard.support-requests.reply', $supportRequest->id) }}" method="POST" class="d-flex gap-2 mt-3">
                            @csrf
                            <input type="text" id="replyMessageInput" name="message" class="form-control" placeholder="{{ __('Type a reply...') }}" required>
                            <button type="submit" class="btn btn-primary">{{ __('Send') }}</button>
                        </form>
                    @else
                        <p class="text-muted mt-3 mb-0">{{ __('Approve this request to start replying.') }}</p>
                    @endif
                @endcan
            </div>
        </div>
    </div>

    <div class="modal fade" id="rejectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form action="{{ route('dashboard.support-requests.reject', $supportRequest->id) }}" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">{{ __('Reject Support Request') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">{{ __('Reason (shown to the driver)') }}</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required></textarea>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-label-secondary" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('Reject') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    <script>
        // window.Echo is created by layouts/master.blade.php's own inline
        // script, which runs *after* this page's own section-script content
        // in the compiled HTML (the shared layout script partial is pulled
        // in earlier, and Echo is set up further down in master.blade.php
        // itself). A plain `if (window.Echo)` truthy check isn't enough --
        // in production this caught some other truthy value squatting on
        // `window.Echo` (observed error: "window.Echo.private is not a
        // function", thrown immediately on page load, before master's own
        // script could have run yet -- most likely a browser extension
        // defining a global with the same name). Check for the actual
        // method the real Echo instance has, not just object presence.
        function isRealEcho(candidate) {
            return !!candidate && typeof candidate.private === 'function';
        }

        function whenEchoReady(callback) {
            if (isRealEcho(window.Echo)) {
                callback();
                return;
            }
            var interval = setInterval(function () {
                if (isRealEcho(window.Echo)) {
                    clearInterval(interval);
                    callback();
                }
            }, 100);
        }

        $(document).ready(function() {
            // AJAX submit for replies -- a plain form post here means a full
            // page reload + the sitewide "Success!" modal on every single
            // chat message, which is a bad fit for a chat UI. The message
            // itself still shows up via the .support.message listener below
            // (broadcasts reach the sender's own open connection too, not
            // just the other party), so this just needs to clear the input.
            var replyForm = document.getElementById('replyForm');
            if (replyForm) {
                replyForm.addEventListener('submit', function (e) {
                    e.preventDefault();

                    var input = document.getElementById('replyMessageInput');
                    var message = input.value.trim();
                    if (!message) return;

                    var submitButton = replyForm.querySelector('button[type="submit"]');
                    submitButton.disabled = true;
                    input.disabled = true;

                    fetch(replyForm.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({ message: message }),
                    })
                        .then(function (response) {
                            if (!response.ok) {
                                return response.json().then(function (data) {
                                    throw new Error(data.message || 'Failed to send reply.');
                                });
                            }
                            input.value = '';
                        })
                        .catch(function (err) {
                            alert(err.message || 'Failed to send reply.');
                        })
                        .finally(function () {
                            submitButton.disabled = false;
                            input.disabled = false;
                            input.focus();
                        });
                });
            }

            whenEchoReady(function () {
                window.Echo.private('support-request.{{ $supportRequest->id }}')
                    .listen('.support.message', function(e) {
                        const isAdmin = e.sender_role === 'admin';
                        const bubble = $('<div>')
                            .addClass('mb-3 d-flex ' + (isAdmin ? 'justify-content-end' : 'justify-content-start'))
                            .html(
                                '<div class="p-3 rounded ' + (isAdmin ? 'bg-label-primary' : 'bg-label-secondary') + '" style="max-width: 70%;">' +
                                '<div class="small fw-bold mb-1">' + (isAdmin ? 'Admin' : 'Driver') + '</div>' +
                                '<div></div>' +
                                '<div class="small text-muted mt-1">just now</div>' +
                                '</div>'
                            );
                        bubble.find('div div:eq(1)').text(e.message);
                        $('#messages-list').append(bubble);
                        $('#messages-list').scrollTop($('#messages-list')[0].scrollHeight);
                    })
                    .listen('.support.request.status', function(e) {
                        location.reload();
                    });
            });
        });
    </script>
@endsection

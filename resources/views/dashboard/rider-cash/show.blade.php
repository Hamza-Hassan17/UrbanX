@extends('layouts.master')

@section('title', __('Rider Cash Ledger'))

@section('breadcrumb-items')
    <li class="breadcrumb-item"><a href="{{ route('dashboard.rider-cash.index') }}">{{ __('Rider Cash') }}</a></li>
    <li class="breadcrumb-item active">{{ $rider->name }}</li>
@endsection

@section('content')
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-6">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Record Settlement') }}</h5>
                        <small class="text-muted">{{ __('Current balance') }}: <strong>{{ \App\Helpers\Helper::formatCurrency($currentBalance) }}</strong> ({{ __('limit') }} {{ \App\Helpers\Helper::formatCurrency($cashLimit) }})</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('dashboard.rider-cash.settle', $rider->id) }}">
                            @csrf
                            <div class="mb-3">
                                <label for="amount" class="form-label">{{ __('Amount') }}</label><span class="text-danger">*</span>
                                <input class="form-control @error('amount') is-invalid @enderror" type="number" step="0.01" min="0.01"
                                    id="amount" name="amount" required value="{{ old('amount') }}" />
                                @error('amount')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="method" class="form-label">{{ __('Method') }}</label><span class="text-danger">*</span>
                                <select id="method" name="method" class="form-select @error('method') is-invalid @enderror" required>
                                    <option value="cash" {{ old('method') == 'cash' ? 'selected' : '' }}>{{ __('Cash') }}</option>
                                    <option value="bank_transfer" {{ old('method') == 'bank_transfer' ? 'selected' : '' }}>{{ __('Bank Transfer') }}</option>
                                    <option value="easypaisa" {{ old('method') == 'easypaisa' ? 'selected' : '' }}>{{ __('Easypaisa') }}</option>
                                    <option value="jazzcash" {{ old('method') == 'jazzcash' ? 'selected' : '' }}>{{ __('JazzCash') }}</option>
                                </select>
                                @error('method')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <div class="mb-3">
                                <label for="note" class="form-label">{{ __('Note') }}</label>
                                <textarea class="form-control @error('note') is-invalid @enderror" id="note" name="note" rows="3">{{ old('note') }}</textarea>
                                @error('note')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <button type="submit" class="btn btn-primary w-100">{{ __('Record Settlement') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Ledger') }} -- {{ $rider->name }}</h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Balance After') }}</th>
                                    <th>{{ __('Method / Reference') }}</th>
                                    <th>{{ __('Recorded By') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($ledger as $entry)
                                    <tr>
                                        <td>{{ $entry->created_at->format('d M, Y h:i A') }}</td>
                                        <td>
                                            @if ($entry->entry_type === 'collected')
                                                <span class="badge bg-label-success">{{ __('Collected') }}</span>
                                            @else
                                                <span class="badge bg-label-warning">{{ __('Settled') }}</span>
                                            @endif
                                        </td>
                                        <td>{{ $entry->entry_type === 'collected' ? '+' : '-' }}{{ \App\Helpers\Helper::formatCurrency($entry->amount) }}</td>
                                        <td>{{ \App\Helpers\Helper::formatCurrency($entry->balance_after) }}</td>
                                        <td>{{ $entry->method ?? ($entry->reference_type ? class_basename($entry->reference_type) . ' #' . $entry->reference_id : '--') }}</td>
                                        <td>{{ $entry->recordedBy->name ?? '--' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">{{ __('No ledger entries yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

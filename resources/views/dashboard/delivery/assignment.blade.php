@extends('layouts.master')

@section('title', __(($mode ?? 'food') === 'parcel' ? 'Manual Parcel Assignment' : 'Manual Delivery Assignment'))

@section('breadcrumb-items')
    <li class="breadcrumb-item active">{{ __(($mode ?? 'food') === 'parcel' ? 'Manual Parcel Assignment' : 'Manual Delivery Assignment') }}</li>
@endsection

@section('css')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @include('dashboard.partials.queue-styles')
    <style>
        :root {
            --primary: #2563eb;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1f2937;
            --gray: #6b7280;
            --border: #e3e1d3;
            --surface: #ffffff;
            --surface-alt: #f6f5ee;
            --page-bg: #ebe9dd;
            --card-shadow: 0 2px 6px -1px rgba(31, 41, 55, 0.08), 0 1px 3px -1px rgba(31, 41, 55, 0.06);
        }
        .dashboard-container { background: var(--page-bg); padding: 6px 2px 2px; border-radius: 14px; display: flex; flex-direction: column; gap: 16px; color: var(--dark); }
        .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; box-shadow: var(--card-shadow); display: flex; flex-direction: column; }
        .section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--gray); margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: var(--primary); width: 16px; }
        .dispatch-toprow { display: grid; grid-template-columns: 320px 200px 1fr; gap: 14px; align-items: stretch; min-height: 560px; }
        .dispatch-toprow.no-form { grid-template-columns: 200px 1fr; }
        .form-panel { padding: 14px; overflow-y: auto; max-height: 760px; }
        .counts-panel { padding: 14px; }
        .map-panel { overflow: hidden; min-height: 560px; }
        #dispatch-map { width: 100%; height: 100%; min-height: 560px; }

        .rider-strip { display: flex; align-items: center; gap: 10px; padding: 10px; border: 1px solid var(--border); border-radius: 10px; background: var(--surface-alt); margin-bottom: 14px; }
        .rider-avatar { width: 38px; height: 38px; border-radius: 10px; background: var(--warning); color: #fff; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
        .rider-avatar.none { background: #9ca3af; }
        .rider-strip h4 { font-size: 14px; margin: 0; }
        .rider-strip small { color: var(--gray); font-size: 11px; display: block; }
        .rider-strip .clear-rider { margin-left: auto; border: none; background: none; color: var(--gray); }

        .field-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .field { display: flex; flex-direction: column; gap: 4px; position: relative; }
        .field-full { grid-column: 1 / -1; }
        .field label { font-size: 11px; font-weight: 700; text-transform: uppercase; color: var(--gray); letter-spacing: .03em; }
        .field label i { color: var(--primary); margin-right: 4px; }
        .field .form-control, .field .form-select { font-size: 13px; background: var(--surface-alt); border-color: var(--border); }
        .subhead { grid-column: 1 / -1; font-size: 11px; font-weight: 700; color: var(--dark); border-bottom: 1px dashed var(--border); padding-bottom: 4px; margin-top: 4px; }
        .pickup-mode { display: flex; gap: 6px; margin-bottom: 4px; }
        .pickup-mode button { flex: 1; font-size: 11px; padding: 4px; border: 1px solid var(--border); border-radius: 6px; background: var(--surface); }
        .pickup-mode button.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .autocomplete-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid var(--border); border-radius: 8px; z-index: 50; max-height: 220px; overflow-y: auto; display: none; box-shadow: var(--card-shadow); }
        .autocomplete-item { padding: 8px 10px; font-size: 12px; cursor: pointer; border-bottom: 1px solid #f1f1f1; }
        .autocomplete-item:hover { background: var(--surface-alt); }
        .autocomplete-item small { color: var(--gray); display: block; }
        .save-job-btn { width: 100%; margin-top: 14px; font-weight: 700; }

        .count-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed var(--border); font-size: 12px; font-weight: 600; text-transform: uppercase; color: var(--gray); }
        .count-row strong { font-size: 20px; }
        .count-available strong { color: var(--secondary); }
        .count-busy strong { color: var(--warning); }
        .count-dispatch strong { color: var(--primary); }
        .count-waiting strong { color: #db2777; }
        .count-booked strong { color: #7c3aed; }
        .count-completed strong { color: var(--secondary); }
        .count-cancelled strong { color: var(--danger); }
        .fare-box { margin-top: auto; padding: 14px; border-radius: 10px; background: linear-gradient(135deg, #fef3c7, #fde68a); border: 1px solid #f59e0b; text-align: center; }
        .fare-box .fare-label { font-size: 11px; font-weight: 700; color: #a16207; text-transform: uppercase; }
        .fare-box .fare-value { font-size: 24px; font-weight: 800; color: #a16207; }
        .fare-box .fare-sub { font-size: 11px; color: #a16207; font-weight: 600; }
        .fare-box .fare-error { font-size: 11px; color: var(--danger); font-weight: 600; }

        .queue-action-btn { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 6px; border: 1px solid var(--primary); background: var(--primary); color: #fff; white-space: nowrap; }
        .queue-action-btn.outline { background: #fff; color: var(--primary); }

        .assign-modal-overlay { position: fixed; inset: 0; background: rgba(17, 24, 39, .5); z-index: 1100; display: none; align-items: center; justify-content: center; padding: 16px; }
        .assign-modal-overlay.show { display: flex; }
        .assign-modal { background: #fff; border-radius: 12px; width: 100%; max-width: 560px; max-height: 90vh; display: flex; flex-direction: column; }
        .assign-modal header { padding: 14px 16px; border-bottom: 1px solid var(--border); }
        .assign-modal header h5 { margin: 0; }
        .assign-modal header small { color: var(--gray); }
        .assign-modal .modal-body-scroll { padding: 12px 16px; overflow-y: auto; }
        .assign-modal footer { padding: 12px 16px; border-top: 1px solid var(--border); display: flex; gap: 8px; justify-content: flex-end; }
        .rider-option { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 6px; cursor: pointer; font-size: 13px; }
        .rider-option:hover { background: var(--surface-alt); }
        .rider-option.selected { border-color: var(--primary); background: #eff6ff; }
        .rider-option.disabled { opacity: .55; }
        .rider-option .meta { color: var(--gray); font-size: 11px; }
        .rider-option .dist { margin-left: auto; font-weight: 700; white-space: nowrap; }
        .badge-cash { background: #fee2e2; color: #b91c1c; font-size: 10px; padding: 2px 6px; border-radius: 6px; font-weight: 700; }
        .badge-busy { background: #fef3c7; color: #a16207; font-size: 10px; padding: 2px 6px; border-radius: 6px; font-weight: 700; }

        .dispatch-toast { position: fixed; top: 20px; right: 20px; z-index: 1200; padding: 12px 16px; border-radius: 10px; color: #fff; font-size: 13px; font-weight: 600; box-shadow: var(--card-shadow); max-width: 380px; display: none; }
        .dispatch-toast.success { background: var(--secondary); }
        .dispatch-toast.error { background: var(--danger); }

        @media (max-width: 1200px) {
            .dispatch-toprow { grid-template-columns: 1fr 1fr; }
            .map-panel { grid-column: 1 / -1; }
        }
        @media (max-width: 768px) {
            .dispatch-toprow { grid-template-columns: 1fr; }
            .field-grid { grid-template-columns: 1fr; }
        }
    </style>
@endsection

@section('content')
    @php $isParcel = ($mode ?? 'food') === 'parcel'; @endphp
    @if ($isParcel && empty($parcelVehicleType))
        <div class="alert alert-warning">{{ __('Choose the Parcel vehicle type in Pricing & Fees → Dispatch. Until then, customer parcels still go to delivery riders and admin parcel booking is disabled.') }}</div>
    @elseif ($isParcel)
        <div class="alert alert-info py-2">{{ __('Parcels go to taxi drivers of the vehicle type:') }} <strong>{{ $parcelVehicleType->name }}</strong></div>
    @endif
    <div class="dashboard-container">
        <div class="dispatch-toprow">

            {{-- Parcel booking form --}}
            {{-- Parcel booking form: Rides workspace only (parcels moved to taxi drivers).
                 Kept in the markup but hidden in food mode so the page script binds unchanged. --}}
            <section class="panel form-panel">
                <div class="rider-strip" id="rider-strip">
                    <div class="rider-avatar none" id="rider-avatar"><i class="fas fa-motorcycle"></i></div>
                    <div>
                        <h4 id="rider-name">{{ __('No rider selected') }}</h4>
                        <small id="rider-meta">{{ __('Job goes to dispatch for riders to claim') }}</small>
                    </div>
                    <button type="button" class="clear-rider" id="clear-rider" title="{{ __('Send to dispatch instead') }}" style="display:none;"><i class="fas fa-xmark"></i></button>
                </div>

                <div class="section-title"><i class="fas {{ $isParcel ? 'fa-box' : 'fa-utensils' }}"></i><span>{{ $isParcel ? __('New Parcel') : __('New Food Delivery') }}</span></div>

                <div class="field-grid">
                    <div class="field field-full">
                        <label for="riderIdInput"><i class="fas fa-id-card"></i>{{ __('Rider #ID') }}</label>
                        <div style="display:flex; gap:6px;">
                            <input type="text" id="riderIdInput" class="form-control" placeholder="{{ __('Optional -- leave empty for dispatch') }}" style="flex:1;">
                            <button type="button" id="nearestRiderBtn" class="btn btn-sm btn-outline-primary" title="{{ __('Nearest available rider to the pickup') }}">
                                <i class="fas fa-location-crosshairs"></i> {{ __('Nearest') }}
                            </button>
                        </div>
                    </div>

                    <div class="field field-full">
                        <label><i class="fas fa-location-dot"></i>{{ $isParcel ? __('Pickup') : __('Pickup restaurant') }}</label>
                        {{-- Restaurant-pickup shortcut hidden: parcels are booked in the Rides workspace now.
                             Markup kept so the page script binds unchanged. --}}
                        <div class="pickup-mode" style="display:none">
                            <button type="button" class="active" data-mode="address">{{ __('Address') }}</button>
                            <button type="button" data-mode="restaurant">{{ __('Restaurant') }}</button>
                        </div>
                        <div id="pickup-address-wrap" style="position:relative;{{ $isParcel ? '' : 'display:none;' }}">
                            <input type="text" id="pickup-input" class="form-control" placeholder="{{ __('Start typing a location...') }}" autocomplete="off">
                            <div class="autocomplete-dropdown" id="pickup-dropdown"></div>
                        </div>
                        <select id="pickup-restaurant" class="form-select" style="{{ $isParcel ? 'display:none;' : '' }}">
                            <option value="">{{ __('Select restaurant') }}</option>
                            @foreach ($restaurants as $restaurant)
                                <option value="{{ $restaurant->id }}" data-lat="{{ $restaurant->latitude }}" data-lng="{{ $restaurant->longitude }}" data-name="{{ $restaurant->name }}">{{ $restaurant->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field field-full">
                        <label for="dropoff-input"><i class="fas fa-flag-checkered"></i>{{ $isParcel ? __('Destination') : __('Customer drop-off') }}</label>
                        <input type="text" id="dropoff-input" class="form-control" placeholder="{{ __('Start typing a destination...') }}" autocomplete="off">
                        <div class="autocomplete-dropdown" id="dropoff-dropdown"></div>
                    </div>

                    @unless($isParcel)
                    <div class="subhead">{{ __('Customer') }}</div>
                    <div class="field">
                        <label for="customer_name"><i class="fas fa-user"></i>{{ __('Name') }}</label>
                        <input type="text" id="customer_name" class="form-control" placeholder="{{ __('Customer name') }}">
                    </div>
                    <div class="field">
                        <label for="customer_phone"><i class="fas fa-phone"></i>{{ __('Phone') }}</label>
                        <input type="text" id="customer_phone" class="form-control" placeholder="{{ __('Phone') }}">
                    </div>
                    <div class="field">
                        <label for="order_amount"><i class="fas fa-receipt"></i>{{ __('Food bill (Rs)') }}</label>
                        <input type="number" id="order_amount" class="form-control" min="0" step="1" placeholder="0">
                    </div>
                    <div class="field">
                        <label for="rider_note"><i class="fas fa-note-sticky"></i>{{ __('Note for rider') }}</label>
                        <input type="text" id="rider_note" class="form-control" placeholder="{{ __('Optional') }}" maxlength="255">
                    </div>
                    @endunless

                    {{-- Parcel-only fields (kept in the DOM in food mode so the script binds unchanged). --}}
                    <div style="display:{{ $isParcel ? 'contents' : 'none' }};">
                    <div class="subhead">{{ __('Sender') }}</div>
                    <div class="field">
                        <label for="sender_name"><i class="fas fa-user"></i>{{ __('Name') }}</label>
                        <input type="text" id="sender_name" class="form-control" placeholder="{{ __('Sender name') }}">
                    </div>
                    <div class="field">
                        <label for="sender_phone"><i class="fas fa-phone"></i>{{ __('Phone') }}</label>
                        <input type="text" id="sender_phone" class="form-control" placeholder="{{ __('Phone') }}">
                    </div>

                    <div class="subhead">{{ __('Receiver') }}</div>
                    <div class="field">
                        <label for="receiver_name"><i class="fas fa-user"></i>{{ __('Name') }}</label>
                        <input type="text" id="receiver_name" class="form-control" placeholder="{{ __('Receiver name') }}">
                    </div>
                    <div class="field">
                        <label for="receiver_phone"><i class="fas fa-phone"></i>{{ __('Phone') }}</label>
                        <input type="text" id="receiver_phone" class="form-control" placeholder="{{ __('Phone') }}">
                    </div>

                    <div class="subhead">{{ __('Package') }}</div>
                    <div class="field">
                        <label for="package_type"><i class="fas fa-box-open"></i>{{ __('Type') }}</label>
                        <select id="package_type" class="form-select">
                            <option value="Documents">{{ __('Documents') }}</option>
                            <option value="Food">{{ __('Food') }}</option>
                            <option value="Clothes">{{ __('Clothes') }}</option>
                            <option value="Electronics">{{ __('Electronics') }}</option>
                            <option value="Groceries">{{ __('Groceries') }}</option>
                            <option value="Other">{{ __('Other') }}</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="package_size"><i class="fas fa-up-right-and-down-left-from-center"></i>{{ __('Size') }}</label>
                        <select id="package_size" class="form-select">
                            <option value="small">{{ __('Small') }}</option>
                            <option value="medium">{{ __('Medium') }}</option>
                            <option value="large">{{ __('Large') }}</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="delivery_fee_paid_by"><i class="fas fa-money-bill"></i>{{ __('Fee paid by') }}</label>
                        <select id="delivery_fee_paid_by" class="form-select">
                            <option value="sender">{{ __('Sender') }}</option>
                            <option value="receiver">{{ __('Receiver') }}</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="scheduled_pickup_at"><i class="fas fa-clock"></i>{{ __('Schedule') }}</label>
                        <input type="datetime-local" id="scheduled_pickup_at" class="form-control" title="{{ __('Leave empty for now') }}">
                    </div>
                    <div class="field field-full">
                        <label for="parcel_notes"><i class="fas fa-note-sticky"></i>{{ __('Notes') }}</label>
                        <input type="text" id="parcel_notes" class="form-control" placeholder="{{ __('Optional') }}" maxlength="500">
                    </div>
                    </div>
                </div>

                <button type="button" class="btn btn-primary save-job-btn" id="save-parcel">
                    <i class="fas fa-paper-plane"></i> {{ __('Save Job') }}
                </button>
                <small class="text-muted d-block mt-2" style="font-size:11px;">
                    {{ $isParcel ? __('Cash on delivery. Fee is calculated from Pricing & Fees.') : __('Cash on delivery. Riders within :km km of the restaurant are highlighted on the map.', ['km' => $foodSettings['max_distance_km'] ?? 4]) }}
                </small>
            </section>

            {{-- Live counters + fare --}}
            <section class="panel counts-panel">
                <div class="section-title"><i class="fas fa-chart-simple"></i><span>{{ __('Live') }}</span></div>
                <div class="count-row count-available"><span>{{ __('Available') }}</span><strong id="stat-available">{{ $stats['available'] }}</strong></div>
                <div class="count-row count-busy"><span>{{ __('Busy') }}</span><strong id="stat-busy">{{ $stats['busy'] }}</strong></div>
                <div class="count-row count-dispatch"><span>{{ __('Dispatch') }}</span><strong id="stat-dispatch">{{ $stats['dispatch'] }}</strong></div>
                @unless($isParcel)
                <div class="count-row count-waiting" title="{{ __('Food orders the restaurant has not accepted yet') }}"><span>{{ __('At restaurant') }}</span><strong id="stat-waiting">{{ $stats['waiting_restaurant'] }}</strong></div>
                @endunless
                <div class="count-row count-booked"><span>{{ __('Booked') }}</span><strong id="stat-booked">{{ $stats['booked'] }}</strong></div>
                <div class="count-row count-completed"><span>{{ __('Completed') }}</span><strong id="stat-completed">{{ $stats['completed'] }}</strong></div>
                <div class="count-row count-cancelled"><span>{{ __('Cancelled') }}</span><strong id="stat-cancelled">{{ $stats['cancelled'] }}</strong></div>

                <div class="fare-box mt-3">
                    <div class="fare-label">{{ __('Delivery Fee') }}</div>
                    <div class="fare-value" id="fare-value">Rs 0</div>
                    <div class="fare-sub" id="fare-sub">0 km</div>
                    <div class="fare-error" id="fare-error"></div>
                </div>
            </section>

            {{-- Map --}}
            <section class="panel map-panel">
                <div id="dispatch-map"></div>
            </section>
        </div>

        @include('dashboard.partials.queue-table', [
            'queueTitle' => $isParcel ? 'Parcel Queue' : 'Delivery Queue',
            'itemPrefix' => $isParcel ? 'PCL-' : 'DEL-',
            'rides' => $rides,
            'queuePresets' => $queuePresets,
            'queueTypeChips' => [],
            'queueEditable' => false,
        ])
    </div>

    {{-- Assign / Reassign dialog --}}
    <div class="assign-modal-overlay" id="assign-modal">
        <div class="assign-modal">
            <header>
                <h5 id="assign-title">{{ __('Assign Rider') }}</h5>
                <small id="assign-subtitle"></small>
            </header>
            <div class="modal-body-scroll">
                <input type="text" id="assign-search" class="form-control form-control-sm mb-2" placeholder="{{ __('Search rider by name, ID or city') }}">
                <div id="assign-rider-list"></div>
                <div class="row g-2 mt-1">
                    <div class="col-4">
                        <label class="form-label small mb-1">{{ __('ETA (min)') }}</label>
                        <input type="number" id="assign-eta" class="form-control form-control-sm" min="0" value="15">
                    </div>
                    <div class="col-8" id="assign-reason-wrap" style="display:none;">
                        <label class="form-label small mb-1">{{ __('Reason for reassigning') }}</label>
                        <input type="text" id="assign-reason" class="form-control form-control-sm" maxlength="500" placeholder="{{ __('e.g. rider not responding') }}">
                    </div>
                </div>
            </div>
            <footer>
                <button type="button" class="btn btn-label-secondary btn-sm" id="assign-cancel">{{ __('Cancel') }}</button>
                <button type="button" class="btn btn-primary btn-sm" id="assign-submit" disabled>{{ __('Assign') }}</button>
            </footer>
        </div>
    </div>

    <div class="dispatch-toast" id="dispatch-toast"></div>
@endsection

@section('script')
    @php $isParcel = ($mode ?? 'food') === 'parcel'; @endphp
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}"></script>
    <script>
        const DISPATCH = {
            statsUrl: @json(route($isParcel ? 'dashboard.parcel-assignment.stats' : 'dashboard.delivery-assignment.stats')),
            quoteUrl: @json(route($isParcel ? 'dashboard.parcel-assignment.quote' : 'dashboard.delivery-assignment.quote')),
            parcelUrl: @json(route($isParcel ? 'dashboard.parcel-assignment.parcel' : 'dashboard.delivery-assignment.parcel')),
            assignUrlTemplate: @json(route('dashboard.rides.update', ['ride' => '__ID__'])),
            reassignUrlTemplate: @json(route($isParcel ? 'dashboard.parcel-assignment.reassign' : 'dashboard.delivery-assignment.reassign', ['ride_id' => '__ID__'])),
            rideShowUrlTemplate: @json(route('dashboard.rides.show', ['ride' => '__ID__'])),
            canReassign: @json(auth()->user()->can('reassign ride')),
            isParcel: @json($isParcel),
            foodUrl: @json($isParcel ? null : route('dashboard.delivery-assignment.food')),
            food: @json($foodSettings ?? null),
            csrf: document.querySelector('meta[name="csrf-token"]').content,
        };

        let riders = @json($riders);
        let pins = @json($pins);

        function esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        }

        function toast(message, type = 'success') {
            const el = document.getElementById('dispatch-toast');
            el.textContent = message;
            el.className = `dispatch-toast ${type}`;
            el.style.display = 'block';
            clearTimeout(el._timer);
            el._timer = setTimeout(() => { el.style.display = 'none'; }, 5000);
        }

        function distanceKm(lat1, lng1, lat2, lng2) {
            const toRad = d => d * Math.PI / 180;
            const dLat = toRad(lat2 - lat1);
            const dLng = toRad(lng2 - lng1);
            const a = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) ** 2;
            return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        }

        // Batch 3 Part 4 -- online and available (offline riders get no offers).
        function isReady(r) {
            return r.presence !== 'offline' && r.status === 'available';
        }

        function ridersByDistance(lat, lng) {
            return riders.map(r => ({
                ...r,
                distance: (r.lat !== null && r.lng !== null && lat !== null) ? distanceKm(lat, lng, r.lat, r.lng) : null,
            })).sort((a, b) => {
                const rank = r => (isReady(r) && !r.over_cash_limit ? 0 : (isReady(r) ? 1 : (r.presence === 'offline' ? 3 : 2)));
                if (rank(a) !== rank(b)) return rank(a) - rank(b);
                if (a.distance === null) return 1;
                if (b.distance === null) return -1;
                return a.distance - b.distance;
            });
        }

        // ---------------- Map ----------------
        let map, pickupMarker = null, dropoffMarker = null, routeLine = null;
        let riderMarkers = [], pinMarkers = [];

        function circleIcon(color, label, ring) {
            const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="34" height="34">` +
                (ring ? `<circle cx="17" cy="17" r="15" fill="none" stroke="#dc2626" stroke-width="3"/>` : '') +
                `<circle cx="17" cy="17" r="11" fill="${color}" stroke="white" stroke-width="2"/>` +
                `<text x="17" y="21" font-size="9" font-family="Arial" font-weight="700" fill="white" text-anchor="middle">${label}</text></svg>`;
            return {
                url: 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg),
                scaledSize: new google.maps.Size(34, 34),
                anchor: new google.maps.Point(17, 17),
            };
        }

        function initMap() {
            map = new google.maps.Map(document.getElementById('dispatch-map'), {
                center: { lat: 24.8607, lng: 67.0011 },
                zoom: 11,
            });
            renderMapLayers(true);
            // Food mode picks the restaurant from the list, so only the drop-off.
            map.addListener('click', e => openMapPick(e.latLng, DISPATCH.isParcel
                ? [{ key: 'pickup', label: 'Pickup', cls: 'btn-primary' }, { key: 'dropoff', label: 'Destination', cls: 'btn-danger' }]
                : [{ key: 'dropoff', label: 'Customer drop-off', cls: 'btn-danger' }]));
        }

        // Pick a point straight from the map (for places the search can't find).
        async function reverseGeocode(lat, lng) {
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18`);
                if (res.ok) {
                    const data = await res.json();
                    if (data && data.display_name) return data.display_name;
                }
            } catch (e) {}
            return `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
        }
        let mapPickInfo = null;
        function openMapPick(latLng, kinds) {
            if (mapPickInfo) mapPickInfo.close();
            const lat = latLng.lat(), lng = latLng.lng();
            const buttons = kinds.map(k =>
                `<button type="button" class="btn btn-sm ${k.cls}" style="margin:2px" data-map-pick="${k.key}" data-lat="${lat}" data-lng="${lng}">${k.label}</button>`).join('');
            mapPickInfo = new google.maps.InfoWindow({ position: latLng, content: `<div style="font-size:12px;margin-bottom:4px">Use this point as:</div>${buttons}` });
            mapPickInfo.open(map);
        }

        document.addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-map-pick]');
            if (!btn) return;
            const lat = parseFloat(btn.dataset.lat), lng = parseFloat(btn.dataset.lng);
            const kind = btn.dataset.mapPick;
            if (mapPickInfo) mapPickInfo.close();
            const input = document.getElementById(kind === 'pickup' ? 'pickup-input' : 'dropoff-input');
            input.value = 'Finding address...';
            setPoint(kind, { lat, lng });
            input.value = await reverseGeocode(lat, lng);
        });

        // Food mode: only riders within the radius of the restaurant are offered.
        let radiusCircle = null;
        function inRadius(r) {
            if (DISPATCH.isParcel || !pickupCoords || r.lat === null || r.lng === null) return true;
            return distanceKm(pickupCoords.lat, pickupCoords.lng, r.lat, r.lng) <= DISPATCH.food.max_distance_km;
        }
        function drawRadius() {
            if (radiusCircle) { radiusCircle.setMap(null); radiusCircle = null; }
            if (DISPATCH.isParcel || !pickupCoords) return;
            radiusCircle = new google.maps.Circle({
                map, center: pickupCoords, radius: DISPATCH.food.max_distance_km * 1000,
                strokeColor: '#10b981', strokeOpacity: .8, strokeWeight: 2, fillColor: '#10b981', fillOpacity: .07, clickable: false,
            });
        }
        function fitCapped(bounds, padding) {
            map.fitBounds(bounds, padding);
            google.maps.event.addListenerOnce(map, 'idle', () => { if (map.getZoom() > 15) map.setZoom(15); });
        }

        function renderMapLayers(fit) {
            riderMarkers.forEach(m => m.setMap(null));
            pinMarkers.forEach(m => m.setMap(null));
            riderMarkers = [];
            pinMarkers = [];
            const bounds = new google.maps.LatLngBounds();
            let hasPoints = false;

            riders.forEach(r => {
                if (r.lat === null || r.lng === null) return;
                const color = r.presence === 'offline' ? '#9ca3af' : (r.status === 'available' ? '#10b981' : '#f59e0b');
                const marker = new google.maps.Marker({
                    position: { lat: r.lat, lng: r.lng },
                    map,
                    icon: circleIcon(color, 'R', r.over_cash_limit),
                    title: `${r.name} (#${r.id})`,
                    opacity: inRadius(r) ? 1 : 0.3,
                });
                const info = new google.maps.InfoWindow({
                    content: `<strong>${esc(r.name)}</strong> #${r.id}<br>${esc(r.presence === 'offline' ? 'offline' : r.status)}${r.city ? ' -- ' + esc(r.city) : ''}<br>` +
                        `Cash: Rs ${Math.round(r.cash_balance)}${r.over_cash_limit ? ' <b style="color:#dc2626">(over limit)</b>' : ''}<br>` +
                        `<a href="#" data-pick-rider="${r.id}">${DISPATCH.isParcel ? 'Use for new parcel' : 'Use for new delivery'}</a>`,
                });
                marker.addListener('click', () => info.open(map, marker));
                riderMarkers.push(marker);
                bounds.extend(marker.getPosition());
                hasPoints = true;
            });

            pins.forEach(p => {
                const marker = new google.maps.Marker({
                    position: { lat: p.lat, lng: p.lng },
                    map,
                    icon: circleIcon(p.type === 'food' ? '#ea580c' : '#2563eb', p.type === 'food' ? 'F' : 'P', false),
                    title: `DEL-${p.ride_id} waiting for a rider`,
                });
                marker.addListener('click', () => openAssignModal({ id: p.ride_id, type_label: p.type === 'food' ? 'Food' : 'Parcel', pickup_lat: p.lat, pickup_lng: p.lng }, 'assign'));
                pinMarkers.push(marker);
                bounds.extend(marker.getPosition());
                hasPoints = true;
            });

            if (fit && hasPoints) fitCapped(bounds, 60);
        }

        document.addEventListener('click', function (e) {
            const link = e.target.closest('[data-pick-rider]');
            if (!link) return;
            e.preventDefault();
            selectFormRider(parseInt(link.dataset.pickRider, 10));
        });

        function drawRoute() {
            if (routeLine) { routeLine.setMap(null); routeLine = null; }
            drawRadius();
            renderMapLayers(false);
            if (pickupCoords && !dropoffCoords && radiusCircle) { map.fitBounds(radiusCircle.getBounds(), 20); return; }
            if (!pickupCoords || !dropoffCoords) return;
            routeLine = new google.maps.Polyline({
                path: [pickupCoords, dropoffCoords],
                strokeOpacity: 0,
                icons: [{ icon: { path: 'M 0,-1 0,1', strokeOpacity: 1, strokeColor: '#7c3aed', scale: 3 }, offset: '0', repeat: '12px' }],
                map,
            });
            const bounds = new google.maps.LatLngBounds();
            bounds.extend(pickupCoords);
            bounds.extend(dropoffCoords);
            fitCapped(bounds, 80);
        }

        function setPoint(kind, coords) {
            if (kind === 'pickup') {
                pickupCoords = coords;
                if (pickupMarker) pickupMarker.setMap(null);
                pickupMarker = coords ? new google.maps.Marker({ position: coords, map, icon: circleIcon('#4e54c8', 'A', false), title: 'Pickup' }) : null;
            } else {
                dropoffCoords = coords;
                if (dropoffMarker) dropoffMarker.setMap(null);
                dropoffMarker = coords ? new google.maps.Marker({ position: coords, map, icon: circleIcon('#dc3545', 'B', false), title: 'Destination' }) : null;
            }
            drawRoute();
            quoteFare();
        }

        // ---------------- Booking form ----------------
        let pickupCoords = null, dropoffCoords = null, formRider = null, searchTimer = null;

        function selectFormRider(riderId) {
            const rider = riders.find(r => r.id === riderId);
            if (!rider) {
                toast(`No delivery rider found with ID ${riderId}`, 'error');
                return;
            }
            formRider = rider;
            document.getElementById('riderIdInput').value = rider.id;
            const avatar = document.getElementById('rider-avatar');
            avatar.classList.remove('none');
            avatar.textContent = rider.name.substring(0, 2).toUpperCase();
            document.getElementById('rider-name').textContent = rider.name;
            let meta = `ID: ${rider.id}${rider.vehicle ? ' · ' + rider.vehicle : ''} · ${rider.presence === 'offline' ? 'offline' : rider.status}`;
            if (rider.over_cash_limit) meta += ` · over cash limit (Rs ${Math.round(rider.cash_balance)})`;
            document.getElementById('rider-meta').textContent = meta;
            document.getElementById('rider-meta').style.color = rider.over_cash_limit ? '#dc2626' : '';
            document.getElementById('clear-rider').style.display = '';
        }

        function clearFormRider() {
            formRider = null;
            document.getElementById('riderIdInput').value = '';
            const avatar = document.getElementById('rider-avatar');
            avatar.classList.add('none');
            avatar.innerHTML = '<i class="fas fa-motorcycle"></i>';
            document.getElementById('rider-name').textContent = 'No rider selected';
            document.getElementById('rider-meta').textContent = 'Job goes to dispatch for riders to claim';
            document.getElementById('rider-meta').style.color = '';
            document.getElementById('clear-rider').style.display = 'none';
        }

        document.getElementById('clear-rider').addEventListener('click', clearFormRider);

        document.getElementById('riderIdInput').addEventListener('change', function () {
            const id = parseInt(this.value, 10);
            if (!this.value.trim()) { clearFormRider(); return; }
            if (!isNaN(id)) selectFormRider(id);
        });

        document.getElementById('nearestRiderBtn').addEventListener('click', function () {
            if (!pickupCoords) { toast('Set the pickup first', 'error'); return; }
            const best = ridersByDistance(pickupCoords.lat, pickupCoords.lng)
                .find(r => isReady(r) && !r.over_cash_limit && r.distance !== null && inRadius(r));
            if (!best) { toast(DISPATCH.isParcel ? 'No available rider with a known location' : `No available rider within ${DISPATCH.food.max_distance_km} km of the restaurant`, 'error'); return; }
            selectFormRider(best.id);
            toast(`${best.name} is ${best.distance.toFixed(1)} km from pickup`);
        });

        document.querySelectorAll('.pickup-mode button').forEach(btn => btn.addEventListener('click', function () {
            document.querySelectorAll('.pickup-mode button').forEach(b => b.classList.toggle('active', b === this));
            const restaurantMode = this.dataset.mode === 'restaurant';
            document.getElementById('pickup-address-wrap').style.display = restaurantMode ? 'none' : '';
            document.getElementById('pickup-restaurant').style.display = restaurantMode ? '' : 'none';
            setPoint('pickup', null);
            document.getElementById('pickup-input').value = '';
            document.getElementById('pickup-restaurant').value = '';
        }));

        document.getElementById('pickup-restaurant').addEventListener('change', function () {
            const opt = this.selectedOptions[0];
            if (!opt || !opt.value) { setPoint('pickup', null); return; }
            setPoint('pickup', { lat: parseFloat(opt.dataset.lat), lng: parseFloat(opt.dataset.lng) });
            if (!document.getElementById('sender_name').value) {
                document.getElementById('sender_name').value = opt.dataset.name;
            }
        });

        async function searchLocations(query) {
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=pk`);
                if (!res.ok) return [];
                const data = await res.json();
                return data.map(i => ({ name: i.display_name.split(',')[0], address: i.display_name, lat: parseFloat(i.lat), lng: parseFloat(i.lon) }));
            } catch (e) {
                return [];
            }
        }

        function wireAutocomplete(inputId, dropdownId, kind) {
            const input = document.getElementById(inputId);
            const dropdown = document.getElementById(dropdownId);
            input.addEventListener('input', function () {
                const q = this.value.trim();
                clearTimeout(searchTimer);
                if (q.length < 3) { dropdown.style.display = 'none'; return; }
                dropdown.innerHTML = '<div class="autocomplete-item">Searching...</div>';
                dropdown.style.display = 'block';
                searchTimer = setTimeout(async () => {
                    const results = await searchLocations(q);
                    if (!results.length) { dropdown.innerHTML = '<div class="autocomplete-item">No locations found</div>'; return; }
                    dropdown.innerHTML = results.map((r, i) =>
                        `<div class="autocomplete-item" data-i="${i}">${esc(r.name)}<small>${esc(r.address)}</small></div>`).join('');
                    dropdown.querySelectorAll('.autocomplete-item').forEach(item => item.addEventListener('click', () => {
                        const r = results[parseInt(item.dataset.i, 10)];
                        input.value = r.address;
                        dropdown.style.display = 'none';
                        setPoint(kind, { lat: r.lat, lng: r.lng });
                    }));
                }, 400);
            });
            document.addEventListener('click', e => {
                if (!dropdown.contains(e.target) && e.target !== input) dropdown.style.display = 'none';
            });
        }
        wireAutocomplete('pickup-input', 'pickup-dropdown', 'pickup');
        wireAutocomplete('dropoff-input', 'dropoff-dropdown', 'dropoff');

        let lastQuote = null;
        async function quoteFare() {
            const value = document.getElementById('fare-value');
            const sub = document.getElementById('fare-sub');
            const err = document.getElementById('fare-error');
            err.textContent = '';
            lastQuote = null;
            if (!pickupCoords || !dropoffCoords) { value.textContent = 'Rs 0'; sub.textContent = '0 km'; return; }
            if (!DISPATCH.isParcel) {
                // Display estimate only -- the server prices it again on save.
                const km = distanceKm(pickupCoords.lat, pickupCoords.lng, dropoffCoords.lat, dropoffCoords.lng);
                const charged = Math.max(Math.ceil(km), 1);
                const fee = DISPATCH.food.first_km_fee + (charged - 1) * DISPATCH.food.per_km_fee;
                value.textContent = `Rs ${Math.round(fee)}`;
                sub.textContent = `${km.toFixed(2)} km (charged ${charged} km)`;
                return;
            }
            try {
                const res = await fetch(DISPATCH.quoteUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': DISPATCH.csrf },
                    body: JSON.stringify({
                        pickup_latitude: pickupCoords.lat, pickup_longitude: pickupCoords.lng,
                        dropoff_latitude: dropoffCoords.lat, dropoff_longitude: dropoffCoords.lng,
                    }),
                });
                const data = await res.json();
                if (!res.ok) {
                    value.textContent = 'Rs --';
                    sub.textContent = '';
                    err.textContent = data.message || 'Could not price this route';
                    return;
                }
                lastQuote = data;
                value.textContent = `Rs ${Math.round(data.delivery_fee)}`;
                sub.textContent = `${data.distance_actual_km} km (charged ${data.distance_charged_km} km)`;
            } catch (e) {
                err.textContent = 'Could not price this route';
            }
        }

        document.getElementById('save-parcel').addEventListener('click', async function () {
            if (!pickupCoords || !dropoffCoords) { toast(DISPATCH.isParcel ? 'Set both pickup and destination' : 'Pick the restaurant and the customer drop-off', 'error'); return; }
            const val = id => document.getElementById(id).value.trim();
            if (!DISPATCH.isParcel) { saveFoodDelivery(this, val); return; }
            for (const [id, label] of [['sender_name', 'Sender name'], ['sender_phone', 'Sender phone'], ['receiver_name', 'Receiver name'], ['receiver_phone', 'Receiver phone']]) {
                if (!val(id)) { toast(`${label} is required`, 'error'); document.getElementById(id).focus(); return; }
            }
            if (formRider && formRider.over_cash_limit &&
                !confirm(`${formRider.name} is over the cash-in-hand limit (Rs ${Math.round(formRider.cash_balance)}). Assign anyway?`)) {
                return;
            }

            const btn = this;
            btn.disabled = true;
            try {
                const res = await fetch(DISPATCH.parcelUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': DISPATCH.csrf },
                    body: JSON.stringify({
                        pickup_latitude: pickupCoords.lat, pickup_longitude: pickupCoords.lng,
                        dropoff_latitude: dropoffCoords.lat, dropoff_longitude: dropoffCoords.lng,
                        sender_name: val('sender_name'), sender_phone: val('sender_phone'),
                        receiver_name: val('receiver_name'), receiver_phone: val('receiver_phone'),
                        package_type: val('package_type'), package_size: val('package_size'),
                        delivery_fee_paid_by: val('delivery_fee_paid_by'),
                        parcel_notes: val('parcel_notes') || null,
                        scheduled_pickup_at: val('scheduled_pickup_at') || null,
                        rider_id: formRider ? formRider.id : null,
                    }),
                });
                const data = await res.json();
                if (!res.ok) { toast(data.message || 'Could not book parcel', 'error'); return; }
                toast(data.message);
                resetForm();
                refreshQueue();
                refreshStats();
            } catch (e) {
                toast('Could not book parcel', 'error');
            } finally {
                btn.disabled = false;
            }
        });

        async function saveFoodDelivery(btn, val) {
            for (const [id, label] of [['customer_name', 'Customer name'], ['customer_phone', 'Customer phone']]) {
                if (!val(id)) { toast(`${label} is required`, 'error'); document.getElementById(id).focus(); return; }
            }
            if (formRider && !inRadius(formRider) &&
                !confirm(`${formRider.name} is more than ${DISPATCH.food.max_distance_km} km from the restaurant. Assign anyway?`)) {
                return;
            }
            if (formRider && formRider.over_cash_limit &&
                !confirm(`${formRider.name} is over the cash-in-hand limit (Rs ${Math.round(formRider.cash_balance)}). Assign anyway?`)) {
                return;
            }
            btn.disabled = true;
            try {
                const res = await fetch(DISPATCH.foodUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': DISPATCH.csrf },
                    body: JSON.stringify({
                        restaurant_id: val('pickup-restaurant'),
                        dropoff_latitude: dropoffCoords.lat, dropoff_longitude: dropoffCoords.lng,
                        customer_name: val('customer_name'), customer_phone: val('customer_phone'),
                        order_amount: val('order_amount') || 0,
                        rider_note: val('rider_note') || null,
                        rider_id: formRider ? formRider.id : null,
                    }),
                });
                const data = await res.json();
                if (!res.ok) { toast(data.message || 'Could not book delivery', 'error'); return; }
                toast(data.message);
                ['customer_name', 'customer_phone', 'order_amount', 'rider_note'].forEach(id => { document.getElementById(id).value = ''; });
                resetForm();
                refreshQueue();
                refreshStats();
            } catch (e) {
                toast('Could not book delivery', 'error');
            } finally {
                btn.disabled = false;
            }
        }

        function resetForm() {
            ['pickup-input', 'dropoff-input', 'sender_name', 'sender_phone', 'receiver_name', 'receiver_phone', 'parcel_notes', 'scheduled_pickup_at']
                .forEach(id => { document.getElementById(id).value = ''; });
            document.getElementById('pickup-restaurant').value = '';
            setPoint('pickup', null);
            setPoint('dropoff', null);
            clearFormRider();
        }

        // ---------------- Stats polling ----------------
        async function refreshStats() {
            try {
                const res = await fetch(DISPATCH.statsUrl, { headers: { 'Accept': 'application/json' } });
                if (!res.ok) return;
                const data = await res.json();
                const s = data.stats;
                document.getElementById('stat-available').textContent = s.available;
                document.getElementById('stat-busy').textContent = s.busy;
                document.getElementById('stat-dispatch').textContent = s.dispatch;
                const waitingEl = document.getElementById('stat-waiting');
                if (waitingEl) waitingEl.textContent = s.waiting_restaurant;
                document.getElementById('stat-booked').textContent = s.booked;
                document.getElementById('stat-completed').textContent = s.completed;
                document.getElementById('stat-cancelled').textContent = s.cancelled;
                riders = data.riders;
                pins = data.pins;
                renderMapLayers(false);
            } catch (e) {
                console.error('Stats refresh failed', e);
            }
        }

        // ---------------- Queue actions + assign dialog ----------------
        window.renderQueueActions = function (ride) {
            const payload = esc(JSON.stringify({ id: ride.id, type_label: ride.type_label, pickup_lat: ride.pickup_lat, pickup_lng: ride.pickup_lng, driver_id: ride.driver_id, driver: ride.driver }));
            const view = `<a class="queue-action-btn outline" href="${DISPATCH.rideShowUrlTemplate.replace('__ID__', ride.id)}" title="View"><i class="fas fa-eye"></i></a>`;
            if (ride.assignable) {
                return `<div style="display:flex;gap:4px;"><button type="button" class="queue-action-btn" data-assign='${payload}'><i class="fas fa-user-plus"></i> Assign</button>${view}</div>`;
            }
            if (ride.reassignable && DISPATCH.canReassign) {
                return `<div style="display:flex;gap:4px;"><button type="button" class="queue-action-btn outline" data-reassign='${payload}'><i class="fas fa-right-left"></i> Reassign</button>${view}</div>`;
            }
            if (ride.queue === 'dispatch' && ride.type_key === 'food') {
                return `<span class="muted-cell" title="Restaurant has not accepted yet">At restaurant</span>`;
            }
            return view;
        };

        let modalState = null;

        function openAssignModal(job, mode) {
            modalState = { job, mode, riderId: null };
            document.getElementById('assign-title').textContent = mode === 'reassign' ? `Reassign DEL-${job.id}` : `Assign DEL-${job.id}`;
            document.getElementById('assign-subtitle').textContent = mode === 'reassign'
                ? `${job.type_label} -- currently with ${job.driver ?? 'a rider'}`
                : `${job.type_label} -- riders sorted by distance to pickup`;
            document.getElementById('assign-reason-wrap').style.display = mode === 'reassign' ? '' : 'none';
            document.getElementById('assign-reason').value = '';
            document.getElementById('assign-eta').value = 15;
            document.getElementById('assign-search').value = '';
            document.getElementById('assign-submit').textContent = mode === 'reassign' ? 'Reassign' : 'Assign';
            renderRiderList();
            document.getElementById('assign-modal').classList.add('show');
        }

        function closeAssignModal() {
            document.getElementById('assign-modal').classList.remove('show');
            modalState = null;
        }

        function renderRiderList() {
            const { job, riderId, mode } = modalState;
            const q = document.getElementById('assign-search').value.trim().toLowerCase();
            const list = ridersByDistance(job.pickup_lat ?? null, job.pickup_lng ?? null)
                .filter(r => !(mode === 'reassign' && r.id === job.driver_id))
                .filter(r => !q || r.name.toLowerCase().includes(q) || String(r.id) === q || (r.city ?? '').toLowerCase().includes(q));

            document.getElementById('assign-rider-list').innerHTML = list.length ? list.map(r => `
                <div class="rider-option ${r.id === riderId ? 'selected' : ''} ${!isReady(r) ? 'disabled' : ''}" data-rider="${r.id}">
                    <div>
                        <div><strong>${esc(r.name)}</strong> <span class="meta">#${r.id}</span>
                            ${r.presence === 'offline' ? '<span class="badge-busy" style="background:#e5e7eb;color:#4b5563">OFFLINE</span>' : (r.status !== 'available' ? '<span class="badge-busy">BUSY</span>' : '')}
                            ${r.over_cash_limit ? `<span class="badge-cash">CASH Rs ${Math.round(r.cash_balance)}</span>` : ''}
                        </div>
                        <div class="meta">${esc(r.city ?? 'No city')}${r.vehicle ? ' · ' + esc(r.vehicle) : ''}</div>
                    </div>
                    <div class="dist">${r.distance !== null ? r.distance.toFixed(1) + ' km' : '--'}</div>
                </div>`).join('') : '<div class="text-muted small p-2">No delivery riders found</div>';

            document.getElementById('assign-submit').disabled = !riderId;
        }

        document.getElementById('assign-rider-list').addEventListener('click', function (e) {
            const row = e.target.closest('[data-rider]');
            if (!row || !modalState) return;
            modalState.riderId = parseInt(row.dataset.rider, 10);
            renderRiderList();
        });
        document.getElementById('assign-search').addEventListener('input', () => modalState && renderRiderList());
        document.getElementById('assign-cancel').addEventListener('click', closeAssignModal);
        document.getElementById('assign-modal').addEventListener('click', e => { if (e.target.id === 'assign-modal') closeAssignModal(); });

        document.addEventListener('click', function (e) {
            const assignBtn = e.target.closest('[data-assign]');
            const reassignBtn = e.target.closest('[data-reassign]');
            if (assignBtn) openAssignModal(JSON.parse(assignBtn.dataset.assign), 'assign');
            if (reassignBtn) openAssignModal(JSON.parse(reassignBtn.dataset.reassign), 'reassign');
        });

        document.getElementById('assign-submit').addEventListener('click', async function () {
            if (!modalState || !modalState.riderId) return;
            const rider = riders.find(r => r.id === modalState.riderId);
            if (rider && rider.over_cash_limit &&
                !confirm(`${rider.name} is over the cash-in-hand limit (Rs ${Math.round(rider.cash_balance)}). Assign anyway?`)) {
                return;
            }
            if (rider && !isReady(rider) &&
                !confirm(`${rider.name} is currently ${rider.presence === 'offline' ? 'offline' : 'busy'}. Assign anyway?`)) {
                return;
            }

            const { job, mode, riderId } = modalState;
            const eta = parseInt(document.getElementById('assign-eta').value, 10) || 15;
            const btn = this;
            btn.disabled = true;
            try {
                const isReassign = mode === 'reassign';
                const res = await fetch(isReassign
                    ? DISPATCH.reassignUrlTemplate.replace('__ID__', job.id)
                    : DISPATCH.assignUrlTemplate.replace('__ID__', job.id), {
                    method: isReassign ? 'POST' : 'PUT',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': DISPATCH.csrf },
                    body: JSON.stringify(isReassign
                        ? { driver_id: riderId, eta_minutes: eta, reason: document.getElementById('assign-reason').value.trim() || null }
                        : { ride_id: job.id, status: 'accepted', driver_id: riderId, eta_minutes: eta }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) { toast(data.message || 'Could not assign this delivery', 'error'); return; }
                toast(data.message || `DEL-${job.id} assigned to ${rider ? rider.name : 'rider'}`);
                closeAssignModal();
                refreshQueue();
                refreshStats();
            } catch (e) {
                toast('Could not assign this delivery', 'error');
            } finally {
                btn.disabled = false;
            }
        });

        initMap();
        setInterval(() => { if (!document.hidden) refreshStats(); }, 10000);
    </script>

    @include('dashboard.partials.queue-scripts', [
        'queueUrl' => route($isParcel ? 'dashboard.parcel-assignment.queue' : 'dashboard.delivery.queue'),
        'queuePresetUrl' => route($isParcel ? 'dashboard.parcel-assignment.queue.presets' : 'dashboard.delivery.queue.presets'),
        'queuePresets' => $queuePresets,
        'lastQueueFilters' => $lastQueueFilters,
        'itemPrefix' => $isParcel ? 'PCL-' : 'DEL-',
        'queueTitle' => $isParcel ? 'Parcels' : 'Deliveries',
        'hasTypeChips' => false,
        'queueEditable' => false,
    ])
@endsection

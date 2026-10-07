{{--
    Shared Ride/Order Queue JS (Phase 2 of the admin workspace split).
    Pairs with queue-table.blade.php. Pure vanilla JS (no new framework),
    matching the rest of this page's existing style. refreshQueue() is the
    one entry point for both a filter change and the poll -- the docblock
    on it covers why, and it's the thing to point a future WebSocket event
    at instead of the poll once Soketi/Reverb replaces Firebase here.

    Expected variables:
    - $queueUrl, $queuePresetUrl (routes)
    - $lastQueueFilters (array, may be null) -- the admin's auto-remembered
      last-used filters for this queue. Named presets were removed per
      follow-up instruction; only last-used + Reset to default remain.
    - $itemPrefix (string)      e.g. "RIDE-" / "ORDER-"
    - $queueTitle (string)      used in the count badge text
    - $hasTypeChips (bool)      whether #queue-type-tabs exists on this page
--}}
<script>
    const queueUrl = @json($queueUrl);
    const queuePresetUrl = @json($queuePresetUrl);
    const lastQueueFilters = @json($lastQueueFilters);
    const queueItemPrefix = @json($itemPrefix);
    const customerProfileUrlTemplate = @json(route('dashboard.customers.show', ['id' => '__ID__']));
    const canViewCustomerProfile = @json(auth()->user()->can('view user'));
    const queueTitleLabel = @json($queueTitle);
    const queueHasTypeChips = @json($hasTypeChips);
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const queueStatusBadge = {
        completed: 'status-completed',
        cancelled: 'status-cancelled',
        requested: 'status-pending',
    };
    const DEFAULT_QUEUE_FILTERS = { type: 'all', status: 'all', from: '', until: '', window: '4' };
    const WINDOW_CYCLE = ['1', '2', '4', '8', '12', '24', 'all'];
    let queueFilters = { ...DEFAULT_QUEUE_FILTERS, ...(lastQueueFilters || {}) };
    let queueRequestInFlight = false;

    function renderQueueTable(rides) {
        const tbody = document.getElementById('queue-table-body');
        if (!rides.length) {
            tbody.innerHTML = `<tr id="queue-empty-row"><td colspan="11" class="queue-empty">No items in the queue</td></tr>`;
            return;
        }

        tbody.innerHTML = rides.map(ride => {
            const badgeClass = queueStatusBadge[ride.status] || 'status-active';
            const statusLabel = ride.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            const canEdit = ride.queue === 'dispatch' || ride.queue === 'booked';
            const actionsCell = canEdit
                ? `<button type="button" class="queue-edit-btn" data-id="${ride.id}" data-status="${ride.status}" data-pickup="${ride.pickup ?? ''}" data-dropoff="${ride.dropoff ?? ''}" data-type="${ride.type_key}" data-queue="${ride.queue}" data-fare="${ride.fare ?? ''}"><i class="fas fa-pen"></i> Edit</button>`
                : '--';
            const passengerCell = (ride.passenger_id && ride.passenger && canViewCustomerProfile)
                ? `<a href="${customerProfileUrlTemplate.replace('__ID__', ride.passenger_id)}">${ride.passenger}</a>`
                : (ride.passenger ?? '--');
            return `
                <tr data-queue="${ride.queue}" data-type="${ride.type_key}">
                    <td class="muted-cell">${ride.time ?? ''}</td>
                    <td>${queueItemPrefix}${ride.id}</td>
                    <td><span class="trip-type-badge trip-type-${ride.type_key}">${ride.type_label}</span></td>
                    <td class="muted-cell">${ride.pickup ?? ''}</td>
                    <td class="muted-cell">${ride.dropoff ?? ''}</td>
                    <td>${ride.driver ?? 'Not assigned'}</td>
                    <td>${passengerCell}</td>
                    <td class="muted-cell">${ride.phone ?? '--'}</td>
                    <td>Rs ${Math.round(ride.fare ?? 0)}</td>
                    <td><span class="trip-status ${badgeClass}">${statusLabel}</span></td>
                    <td>${actionsCell}</td>
                </tr>
            `;
        }).join('');
    }

    function queueParams() {
        const params = new URLSearchParams({
            type: queueFilters.type,
            status: queueFilters.status,
            window: queueFilters.window,
        });
        if (queueFilters.from) params.set('from', queueFilters.from);
        if (queueFilters.until) params.set('until', queueFilters.until);
        return params.toString();
    }

    async function refreshQueue() {
        if (queueRequestInFlight) return;
        queueRequestInFlight = true;
        try {
            const response = await fetch(`${queueUrl}?${queueParams()}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!response.ok) return;
            const data = await response.json();
            renderQueueTable(data.rides);
            document.getElementById('queue-count').textContent = `${data.count} ${queueTitleLabel}`;
        } catch (error) {
            console.error('Queue refresh failed:', error);
        } finally {
            queueRequestInFlight = false;
        }
    }

    function syncQueueToolbar() {
        if (queueHasTypeChips) {
            document.querySelectorAll('#queue-type-tabs .queue-tab').forEach(t =>
                t.classList.toggle('active', t.dataset.type === queueFilters.type));
        }
        document.querySelectorAll('#queue-tabs .queue-tab').forEach(t =>
            t.classList.toggle('active', t.dataset.queue === queueFilters.status));
        document.getElementById('qf-from').value = queueFilters.from;
        document.getElementById('qf-until').value = queueFilters.until;
        document.getElementById('qf-window').value = queueFilters.window;
        document.getElementById('qf-now').classList.toggle('active', !queueFilters.from);
    }

    function onQueueChange() {
        syncQueueToolbar();
        refreshQueue();
        fetch(queuePresetUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ filters: queueFilters }),
        }).catch(error => console.error('Saving queue state failed:', error));
    }

    function cycleQueueWindow() {
        const index = WINDOW_CYCLE.indexOf(queueFilters.window);
        queueFilters.window = WINDOW_CYCLE[(index + 1) % WINDOW_CYCLE.length];
        onQueueChange();
    }

    function resetQueueFilters() {
        queueFilters = { ...DEFAULT_QUEUE_FILTERS };
        onQueueChange();
    }

    document.addEventListener('keydown', function(e) {
        if (!e.altKey) return;
        if (e.key.toLowerCase() === 'w') { e.preventDefault(); cycleQueueWindow(); }
        if (e.key.toLowerCase() === 'd') { e.preventDefault(); resetQueueFilters(); }
    });

    if (queueHasTypeChips) {
        document.getElementById('queue-type-tabs').addEventListener('click', function(e) {
            const tab = e.target.closest('.queue-tab');
            if (!tab) return;
            queueFilters.type = tab.dataset.type;
            onQueueChange();
        });
    }

    document.getElementById('queue-tabs').addEventListener('click', function(e) {
        const tab = e.target.closest('.queue-tab');
        if (!tab) return;
        queueFilters.status = tab.dataset.queue;
        onQueueChange();
    });

    document.getElementById('qf-from').addEventListener('change', function() {
        queueFilters.from = this.value;
        onQueueChange();
    });

    document.getElementById('qf-until').addEventListener('change', function() {
        queueFilters.until = this.value;
        onQueueChange();
    });

    document.getElementById('qf-window').addEventListener('change', function() {
        queueFilters.window = this.value;
        onQueueChange();
    });

    document.getElementById('qf-now').addEventListener('click', function() {
        queueFilters.from = '';
        onQueueChange();
    });

    document.getElementById('qf-until-clear').addEventListener('click', function() {
        queueFilters.until = '';
        onQueueChange();
    });

    document.getElementById('qf-reset-default').addEventListener('click', function() {
        resetQueueFilters();
    });

    syncQueueToolbar();
    refreshQueue();
    setInterval(() => {
        if (!document.hidden) refreshQueue();
    }, 10000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshQueue();
    });
</script>

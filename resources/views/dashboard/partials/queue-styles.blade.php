{{--
    Shared CSS for the Ride/Order Queue component (Phase 2 of the admin
    workspace split). Pulled out of custom-rides/index.blade.php's page
    style block so the Delivery workspace's queue page can look the same
    without copy-pasting a few hundred lines of CSS. The Rides page keeps
    its own copy of these rules inline (it already had them, verified
    working, and touching a page's full <style> block carries more risk
    than it's worth here) -- so this file is the one both pages' rules
    trace back to conceptually, even though Rides' copy isn't literally
    @include-ing this file. See the Phase 2 report.
--}}
<style>
    :root {
        --primary: #2563eb;
        --primary-dark: #1d4ed8;
        --secondary: #10b981;
        --danger: #ef4444;
        --warning: #f59e0b;
        --accent: #eab308;
        --accent-dark: #a16207;
        --dark: #1f2937;
        --gray: #6b7280;
        --border: #e3e1d3;
        --surface: #ffffff;
        --surface-alt: #f6f5ee;
        --page-bg: #ebe9dd;
        --card-shadow: 0 2px 6px -1px rgba(31, 41, 55, 0.08), 0 1px 3px -1px rgba(31, 41, 55, 0.06);
    }

    .dashboard-container,
    .dashboard-container .content-wrapper {
        color: var(--dark);
    }

    .dashboard-container {
        background: var(--page-bg);
        padding: 6px 2px 2px;
    }

    .panel {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: var(--card-shadow);
        display: flex;
        flex-direction: column;
    }

    .trips-container {
        padding: 20px;
    }

    .trips-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .trips-header h3 {
        font-size: 15px;
        font-weight: 800;
        color: var(--dark);
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .trip-count {
        background: var(--primary);
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }

    .queue-tabs {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
    }

    .queue-tab {
        border: 1.5px solid var(--border);
        background: var(--surface-alt);
        color: var(--gray);
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        padding: 6px 14px;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .queue-tab:hover {
        border-color: var(--accent);
        color: var(--dark);
    }

    .queue-tab.active {
        background: var(--accent);
        border-color: var(--accent-dark);
        color: #422006;
    }

    .queue-tab:disabled {
        opacity: 0.45;
        cursor: not-allowed;
    }

    .queue-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        padding: 10px 0;
    }

    .queue-toolbar-group {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 6px;
    }

    .queue-select,
    .queue-input {
        border: 1.5px solid var(--border);
        background: var(--surface-alt);
        color: var(--dark);
        font-size: 11px;
        font-weight: 700;
        padding: 5px 10px;
        border-radius: 20px;
    }

    .queue-field {
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        color: var(--gray);
        margin: 0;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .queue-sep {
        font-weight: 800;
        color: var(--gray);
    }

    .queue-table-wrap {
        overflow-x: auto;
    }

    table.queue-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .queue-table thead th {
        text-align: left;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--gray);
        padding: 9px 12px;
        border-bottom: 2px solid var(--border);
        white-space: nowrap;
    }

    .queue-table tbody td {
        padding: 9px 12px;
        border-bottom: 1px solid var(--border);
        color: var(--dark);
        vertical-align: middle;
    }

    .queue-table tbody tr:hover {
        background: var(--surface-alt);
    }

    .queue-table .muted-cell {
        color: var(--gray);
        font-size: 12px;
    }

    .trip-status {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }

    .status-active {
        background: rgba(37, 99, 235, 0.12);
        color: var(--primary);
    }

    .status-completed {
        background: rgba(16, 185, 129, 0.12);
        color: var(--secondary);
    }

    .status-pending {
        background: rgba(234, 179, 8, 0.18);
        color: var(--accent-dark);
    }

    .status-cancelled {
        background: rgba(239, 68, 68, 0.12);
        color: var(--danger);
    }

    .queue-empty {
        padding: 30px;
        text-align: center;
        color: var(--gray);
    }

    .trip-type-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 10px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        white-space: nowrap;
    }

    .trip-type-ride {
        background: rgba(37, 99, 235, 0.12);
        color: var(--primary);
    }

    .trip-type-delivery {
        background: rgba(234, 88, 12, 0.14);
        color: #c2410c;
    }

    .trip-type-food {
        background: rgba(234, 88, 12, 0.14);
        color: #c2410c;
    }

    .trip-type-parcel {
        background: rgba(109, 40, 217, 0.12);
        color: #6d28d9;
    }

    .queue-edit-btn {
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--primary);
        border-radius: 6px;
        padding: 4px 10px;
        font-size: 11px;
        font-weight: 700;
        cursor: pointer;
    }

    .queue-edit-btn:hover {
        background: var(--surface-alt);
    }
</style>

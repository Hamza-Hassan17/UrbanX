<ul class="nav nav-pills mb-4">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.finance.tax-commission') ? 'active' : '' }}"
            href="{{ route('dashboard.finance.tax-commission') }}">{{ __('Tax & Commission') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.finance.reports') ? 'active' : '' }}"
            href="{{ route('dashboard.finance.reports') }}">{{ __('Reports') }}</a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('dashboard.payroll.*') ? 'active' : '' }}"
            href="{{ route('dashboard.payroll.index') }}">{{ __('Payroll') }}</a>
    </li>
    @can(['manage pricing fees'])
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('dashboard.pricing-fees.*') ? 'active' : '' }}"
                href="{{ route('dashboard.pricing-fees.index') }}">{{ __('Pricing & Fees') }}</a>
        </li>
    @endcan
</ul>

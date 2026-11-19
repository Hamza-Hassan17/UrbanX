<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityLog;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CustomerInsightsService as CI;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform -> Customer Insights: customers grouped by how they use Urban
 * (top / at risk / new / regular / inactive / never ordered), with charts,
 * so admins can spot good customers who stopped ordering and win them back.
 */
class CustomerInsightsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('view user');

        $customers = User::role('user')->get(['id', 'name', 'email', 'phone', 'created_at']);
        $all = CI::summaries($customers->pluck('id')->all());
        $average = CI::averageOrders($all);

        $rows = $customers->map(function (User $u) use ($all, $average) {
            $c = $all[$u->id] ?? null;
            return [
                'id' => $u->id,
                'name' => $u->name,
                'phone' => $u->phone,
                'email' => $u->email,
                'group' => CI::groupOf($c, $average),
                'orders' => $c['orders'] ?? 0,
                'spent' => $c['spent'] ?? 0,
                'services' => $c['services'] ?? null,
                'last_at' => $c['last_at'] ?? null,
                'days_since' => isset($c['last_at']) && $c['last_at'] ? (int) $c['last_at']->diffInDays(now()) : null,
            ];
        });

        $groupCounts = array_map(fn () => 0, CI::GROUPS);
        foreach ($rows as $r) {
            $groupCounts[$r['group']]++;
        }

        $group = array_key_exists($request->group, CI::GROUPS) ? $request->group : 'at_risk';
        $search = trim((string) $request->search);
        $list = $rows->where('group', $group)
            ->when($search !== '', fn ($c) => $c->filter(fn ($r) => str_contains(mb_strtolower($r['name'] . ' ' . $r['phone'] . ' ' . $r['email']), mb_strtolower($search))))
            ->sortByDesc(fn ($r) => [$r['orders'], $r['spent']])
            ->take(500)
            ->values();

        $serviceTotals = [];
        foreach (CI::SERVICES as $k => $label) {
            $serviceTotals[$label] = (int) collect($all)->sum(fn ($c) => $c['services'][$k]['completed']);
        }

        return view('dashboard.customers.insights', [
            'groupCounts' => $groupCounts,
            'group' => $group,
            'search' => $search,
            'list' => $list,
            'average' => $average,
            'atRiskDays' => CI::atRiskDays(),
            'serviceTotals' => $serviceTotals,
            'activeByMonth' => $this->activeCustomersByMonth(),
            'totalCustomers' => $customers->count(),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorize('view user');
        $data = $request->validate(['customer_at_risk_days' => 'required|integer|min:1|max:365']);

        $settings = SystemSetting::first();
        $old = (int) ($settings->customer_at_risk_days ?? 14);
        $settings->customer_at_risk_days = (int) $data['customer_at_risk_days'];
        $settings->save();

        AdminActivityLog::record('Changed customer at-risk days', ['customer_at_risk_days' => $old], $data, SystemSetting::class, $settings->id);

        return redirect()->route('dashboard.customer-insights.index', ['group' => 'at_risk'])
            ->with('success', "Customers are now \"at risk\" after {$data['customer_at_risk_days']} days without an order.");
    }

    /** Distinct customers with any (not cancelled) order, per month, last 12 months. */
    private function activeCustomersByMonth(): array
    {
        $since = now()->startOfMonth()->subMonths(11);
        $union = DB::table('rides')->whereIn('ride_type', ['ride', 'parcel'])->where('status', '<>', 'cancelled')->whereNotNull('passenger_id')
                ->whereRaw('coalesce(requested_at, created_at) >= ?', [$since])
                ->selectRaw("passenger_id as uid, date_format(coalesce(requested_at, created_at), '%Y-%m') as ym")
            ->unionAll(DB::table('restaurant_orders')->whereNotIn('status', ['cancelled', 'rejected'])->whereNotNull('customer_id')
                ->where('created_at', '>=', $since)->selectRaw("customer_id as uid, date_format(created_at, '%Y-%m') as ym"))
            ->unionAll(DB::table('bookings')->where('status', '<>', 'cancelled')->whereNotNull('user_id')
                ->where('created_at', '>=', $since)->selectRaw("user_id as uid, date_format(created_at, '%Y-%m') as ym"));

        $counts = DB::query()->fromSub($union, 'a')->selectRaw('ym, count(distinct uid) as n')->groupBy('ym')->pluck('n', 'ym');

        $out = [];
        for ($i = 11; $i >= 0; $i--) {
            $ym = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $out[now()->startOfMonth()->subMonths($i)->format('M y')] = (int) ($counts[$ym] ?? 0);
        }

        return $out;
    }
}

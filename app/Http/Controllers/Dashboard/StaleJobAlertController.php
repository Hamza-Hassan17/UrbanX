<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\RestaurantOrder;
use App\Models\StaleJobAlert;
use App\Services\StaleJobAlertService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Batch 2 Part 3 -- one Alerts page per workspace (rides, delivery),
 * filtered by the `workspace` param set when each alert type is raised
 * (see app:scan-stale-jobs). 'view stale alerts' / 'resolve stale alerts'
 * permissions, granted to Admin/Operator/Dispatcher; Super Admin has every
 * permission via the seeder's "grant all" step, so sees both workspaces.
 */
class StaleJobAlertController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        $this->authorize('view stale alerts');

        $alerts = StaleJobAlert::where('workspace', $workspace)
            ->when(!$request->boolean('show_resolved'), fn ($q) => $q->where('status', 'open'))
            ->with('resolvedBy:id,name')
            ->latest('created_at')
            ->paginate(50);

        $alerts->getCollection()->transform(function ($alert) {
            $alert->subject_url = $this->subjectUrl($alert);
            return $alert;
        });

        return view('dashboard.stale-alerts.index', [
            'alerts' => $alerts,
            'workspace' => $workspace,
            'pageTitle' => __('Alerts'),
        ]);
    }

    /**
     * A RestaurantOrder has no standalone detail page of its own -- it's
     * shown on its related ride's page (rides/show.blade.php already
     * loads $relatedOrder, see Batch 1's cash-discrepancy follow-up), so
     * an order-subject alert links there via ride_id instead.
     */
    private function subjectUrl(StaleJobAlert $alert): ?string
    {
        if ($alert->subject_type === Ride::class) {
            return route('dashboard.rides.show', $alert->subject_id);
        }

        if ($alert->subject_type === RestaurantOrder::class) {
            $order = RestaurantOrder::find($alert->subject_id);
            if ($order && $order->ride_id) {
                return route('dashboard.rides.show', $order->ride_id);
            }
        }

        return null;
    }

    public function resolve(Request $request, StaleJobAlert $alert)
    {
        $this->authorize('resolve stale alerts');

        $validator = Validator::make($request->all(), [
            'note' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->with('error', 'Validation Error!');
        }

        try {
            StaleJobAlertService::resolve($alert, $request->user(), $request->note);

            return redirect()->back()->with('success', 'Alert resolved successfully');
        } catch (\Throwable $th) {
            Log::error('Resolve Stale Alert Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }
}

<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Ride;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    /**
     * Operator (admin/dispatcher) and driver job-count traceability report.
     * Pure aggregation over data that already exists on `rides` -- no new
     * tracking needed beyond the created_by / status_updated_by columns.
     */
    public function index()
    {
        $this->authorize('view ride');

        try {
            // Per-operator: how many rides they manually created, and how many
            // status changes (accept/cancel/etc.) they made from the dashboard.
            $operatorStats = User::query()
                ->select('users.id', 'users.name')
                ->selectSub(
                    Ride::whereColumn('created_by', 'users.id')->selectRaw('count(*)'),
                    'rides_created'
                )
                ->selectSub(
                    Ride::whereColumn('status_updated_by', 'users.id')
                        ->where('status_updated_by_role', 'admin')
                        ->selectRaw('count(*)'),
                    'status_changes_made'
                )
                ->whereHas('roles', function ($q) {
                    $q->whereIn('name', ['admin', 'super-admin']);
                })
                ->having('rides_created', '>', 0)
                ->orHaving('status_changes_made', '>', 0)
                ->orderByDesc('rides_created')
                ->get();

            // Per-driver: total assigned, completed, cancelled.
            $driverStats = User::role('driver')
                ->select('users.id', 'users.name', 'users.driver_status')
                ->selectSub(
                    Ride::whereColumn('driver_id', 'users.id')->selectRaw('count(*)'),
                    'total_assigned'
                )
                ->selectSub(
                    Ride::whereColumn('driver_id', 'users.id')->where('status', 'completed')->selectRaw('count(*)'),
                    'completed'
                )
                ->selectSub(
                    Ride::whereColumn('driver_id', 'users.id')->where('status', 'cancelled')->selectRaw('count(*)'),
                    'cancelled'
                )
                ->having('total_assigned', '>', 0)
                ->orderByDesc('total_assigned')
                ->get();

            return view('dashboard.reports.index', compact('operatorStats', 'driverStats'));
        } catch (\Throwable $th) {
            Log::error('Operator/Driver Report Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }
}

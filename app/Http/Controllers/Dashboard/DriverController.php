<?php

namespace App\Http\Controllers\Dashboard;

use App\Models\User;
use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $this->authorize('view driver');
        try {
            $drivers = $this->driverQuery(delivery: false)->get();
            return view('dashboard.drivers.index', compact('drivers'));
        } catch (\Throwable $th) {
            Log::error('Drivers Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Drivers currently awaiting review -- the "inbox" super-admin checks
     * for new verification submissions, since there's otherwise no way to
     * tell who's pending without opening every driver's profile.
     */
    public function pendingVerifications()
    {
        $this->authorize('view driver');
        try {
            $drivers = $this->driverQuery(delivery: false)
                ->whereHas('driverVerification', function ($q) {
                    $q->where('status', 'submitted');
                })
                ->get();
            return view('dashboard.drivers.pending', compact('drivers'));
        } catch (\Throwable $th) {
            Log::error('Pending Verifications Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Delivery workspace's "Riders" list (follow-up to the admin workspace
     * split) -- same underlying `driver`-role users, same views, filtered
     * to vehicle_type.is_delivery = true. show()/update()/verification
     * approve-reject/documents.pdf are intentionally NOT workspace-gated
     * (see config/workspaces.php) since a driver's profile/KYC is the same
     * resource regardless of which list (taxi or delivery) an admin found
     * them from -- only these two list endpoints are gated, to the
     * Delivery workspace.
     */
    public function deliveryIndex()
    {
        $this->authorize('view driver');
        try {
            $drivers = $this->driverQuery(delivery: true)->get();
            return view('dashboard.drivers.index', [
                'drivers' => $drivers,
                'pageTitle' => __('Riders'),
            ]);
        } catch (\Throwable $th) {
            Log::error('Delivery Riders Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function deliveryPendingVerifications()
    {
        $this->authorize('view driver');
        try {
            $drivers = $this->driverQuery(delivery: true)
                ->whereHas('driverVerification', function ($q) {
                    $q->where('status', 'submitted');
                })
                ->get();
            return view('dashboard.drivers.pending', [
                'drivers' => $drivers,
                'pageTitle' => __('Riders Awaiting Verification'),
                'indexRoute' => route('dashboard.delivery-riders.index'),
                'indexLabel' => __('Riders'),
            ]);
        } catch (\Throwable $th) {
            Log::error('Delivery Pending Verifications Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Not delivery = vehicle_type.is_delivery is false/null, or the driver
     * has no vehicle assigned yet -- same "default to non-special category"
     * rule DeliveryController's Food/Parcel chip already uses.
     */
    private function driverQuery(bool $delivery)
    {
        $query = User::with('profile:id,user_id,phone_number,city', 'driverVerification')->role('driver');

        return $delivery
            ? $query->whereHas('driverVehicle.vehicleType', fn ($q) => $q->where('is_delivery', '1'))
            : $query->whereDoesntHave('driverVehicle.vehicleType', fn ($q) => $q->where('is_delivery', '1'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $this->authorize('view driver');
        try {
            $driver = User::with('driverCnic', 'driverLicense', 'driverSelfie', 'driverVehicle.vehicleType', 'driverVerification', 'profile')->findOrFail($id);
            return view('dashboard.drivers.show', compact('driver'));
        } catch (\Throwable $th) {
            Log::error('Drivers Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    /**
     * Combined PDF of every submitted KYC document (vehicle images/reg
     * paper, license front/back, CNIC front/back, selfie) -- individual
     * JPG downloads are plain <a download> links straight to the storage
     * URL in the view, no controller needed for those.
     */
    public function exportDocumentsPdf(string $id)
    {
        $this->authorize('view driver');
        try {
            $driver = User::with('driverCnic', 'driverLicense', 'driverSelfie', 'driverVehicle.vehicleType')->findOrFail($id);

            $pdf = Pdf::loadView('dashboard.drivers.documents-pdf', compact('driver'))
                ->setPaper('a4', 'portrait');

            return $pdf->download("driver-{$driver->id}-documents.pdf");
        } catch (\Throwable $th) {
            Log::error('Driver Documents PDF Export Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Approve/Reject/Resubmission are the only admin actions on a
     * verification per the spec -- there is no separate audit-log table,
     * the driver_verifications row itself is overwritten each cycle
     * (submitted -> approved|rejected -> re-submitted -> ...).
     */
    public function approveVerification(string $id)
    {
        $this->authorize('update driver');
        try {
            $driver = User::findOrFail($id);
            $verification = $driver->driverVerification()->firstOrCreate(['driver_id' => $driver->id]);
            $verification->status = 'approved';
            $verification->rejection_reason = null;
            $verification->reviewed_by = auth()->id();
            $verification->reviewed_at = now();
            $verification->save();

            return redirect()->back()->with('success', 'Driver verification approved.');
        } catch (\Throwable $th) {
            Log::error('Driver Verification Approve Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    public function rejectVerification(Request $request, string $id)
    {
        $this->authorize('update driver');
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);
        try {
            $driver = User::findOrFail($id);
            $verification = $driver->driverVerification()->firstOrCreate(['driver_id' => $driver->id]);
            $verification->status = 'rejected';
            $verification->rejection_reason = $request->rejection_reason;
            $verification->reviewed_by = auth()->id();
            $verification->reviewed_at = now();
            $verification->save();

            $driver->driver_status = 'busy';
            $driver->save();

            return redirect()->back()->with('success', 'Driver verification rejected.');
        } catch (\Throwable $th) {
            Log::error('Driver Verification Reject Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}

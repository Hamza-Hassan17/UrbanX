<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Complain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ComplainController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * Originally (Phase 4 of the admin workspace split) this filtered by
     * session workspace -- Rides/Delivery only seeing their own service,
     * Platform seeing everything with a ?service= filter. A later, explicit
     * follow-up instruction made Platform a real, separately-enforced
     * workspace (dashboard.complains.* is Platform-only in
     * config/workspaces.php now), so this route is only ever reached with
     * session('workspace') === 'platform' -- the old rides/delivery
     * branches were unreachable dead code and have been removed. The
     * ?service= filter stays; it's still useful for narrowing the list.
     */
    public function index(Request $request)
    {
        $this->authorize('view complain');
        try {
            $complains = Complain::latest()
                ->when($request->filled('service'), fn ($q) => $q->where('service', $request->service))
                ->get();

            return view('dashboard.complains.index', ['complains' => $complains, 'workspace' => session('workspace')]);
        } catch (\Throwable $th) {
            Log::error('Complains Index Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
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
        $this->authorize('view complain');
        try {
            $complain = Complain::with('user')->findOrFail($id);
            return view('dashboard.complains.show', compact('complain'));
        } catch (\Throwable $th) {
            Log::error('Complain Show Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
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
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->authorize('delete complain');
        try {
            $complain = Complain::findOrFail($id);
            $complain->delete();
            return redirect()->back()->with('success', 'Complain deleted successfully');
        } catch (\Throwable $th) {
            Log::error('Complain Deletion Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }

    public function updateStatus(Request $request, string $id)
    {
        $this->authorize('update complain');
        try {
            $complain = Complain::findOrFail($id);
            $complain->status = $request->status;
            $complain->save();

            return redirect()->back()->with('success', 'Complain status updated successfully');
        } catch (\Throwable $th) {
            Log::error('Complain Status Updation Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', "Something went wrong! Please try again later");
            throw $th;
        }
    }
}

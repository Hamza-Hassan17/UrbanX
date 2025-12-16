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
     */
    public function index()
    {
        $this->authorize('view complain');
        try {
            $complains = Complain::latest()->get();
            return view('dashboard.complains.index', compact('complains'));
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

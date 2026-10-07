<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\TermsAndCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TermsController extends Controller
{
    public function index()
    {
        $this->authorize('view terms');

        $current = TermsAndCondition::current();
        $history = TermsAndCondition::with('creator:id,name')->latest('id')->take(10)->get();

        $acceptedCount = $current
            ? \App\Models\User::where('terms_accepted_version_id', $current->id)->count()
            : 0;
        $totalUsers = \App\Models\User::whereDoesntHave('roles', fn ($q) => $q->whereIn(
            'name',
            array_merge(\App\Http\Controllers\Dashboard\User\UserController::ADMIN_PANEL_ROLES, ['dispatcher'])
        ))->count();

        return view('dashboard.terms.index', compact('current', 'history', 'acceptedCount', 'totalUsers'));
    }

    /**
     * Publishing is always an insert, never an update -- that's what makes
     * every user who already accepted the old version get re-prompted
     * automatically (their terms_accepted_version_id stops matching the
     * new current()).
     */
    public function store(Request $request)
    {
        $this->authorize('update terms');

        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', 'Validation Error!');
        }

        try {
            TermsAndCondition::create([
                'content' => $request->content,
                'created_by' => auth()->id(),
            ]);

            return redirect()->route('dashboard.terms.index')->with('success', 'New Terms & Conditions version published. Users who accepted an older version will be prompted again.');
        } catch (\Throwable $th) {
            Log::error('Terms Publish Failed', ['error' => $th->getMessage()]);
            return redirect()->back()->with('error', 'Something went wrong! Please try again later');
        }
    }
}

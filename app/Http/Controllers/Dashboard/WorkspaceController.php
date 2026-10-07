<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    public function switch(Request $request)
    {
        $request->validate([
            'workspace' => 'required|string|in:' . implode(',', array_keys(config('workspaces.workspaces'))),
        ]);

        $user = $request->user();
        $allowed = $user->allowedWorkspaces();

        if (!in_array($request->workspace, $allowed, true)) {
            abort(403, 'You do not have access to this workspace.');
        }

        session(['workspace' => $request->workspace]);
        $user->last_workspace = $request->workspace;
        $user->save();

        $landing = config("workspaces.workspaces.{$request->workspace}.landing_route", 'dashboard');

        return redirect()->route($landing)->with('success', 'Switched workspace.');
    }
}

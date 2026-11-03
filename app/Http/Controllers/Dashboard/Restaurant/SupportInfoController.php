<?php

namespace App\Http\Controllers\Dashboard\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\EatsSupportInfo;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

class SupportInfoController extends Controller
{
    use AuthorizesRequests;

    public function edit()
    {
        $this->authorize('view restaurant category');

        $info = EatsSupportInfo::first() ?? new EatsSupportInfo();

        return view('dashboard.restaurant.support-info.edit', compact('info'));
    }

    public function update(Request $request)
    {
        $this->authorize('update restaurant category');

        $data = $request->validate([
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'about' => 'nullable|string|max:5000',
            'facebook_url' => 'nullable|url|max:255',
            'instagram_url' => 'nullable|url|max:255',
            'twitter_url' => 'nullable|url|max:255',
        ]);

        $info = EatsSupportInfo::first() ?? new EatsSupportInfo();
        $info->fill($data)->save();

        return redirect()->back()->with('success', 'Contact & About updated.');
    }
}

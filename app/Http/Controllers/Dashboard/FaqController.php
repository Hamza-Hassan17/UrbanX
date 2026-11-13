<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;

/**
 * Questions and answers shown on the apps' Help & Support screens.
 */
class FaqController extends Controller
{
    use AuthorizesRequests;

    public const AUDIENCES = [
        'all' => 'All apps',
        'restaurant' => 'Restaurants (Partner app)',
        'rider' => 'Delivery riders',
        'driver' => 'Taxi drivers',
        'customer' => 'Customers',
    ];

    public function index(Request $request)
    {
        $this->authorize('view support requests');

        $faqs = Faq::when($request->filled('audience'), fn ($q) => $q->where('audience', $request->audience))
            ->orderBy('audience')->orderBy('sort_order')->orderBy('id')->get();
        $editing = $request->filled('edit') ? Faq::find($request->edit) : null;

        return view('dashboard.faqs.index', ['faqs' => $faqs, 'editing' => $editing, 'audiences' => self::AUDIENCES]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage support requests');
        Faq::create($this->validated($request));

        return redirect()->route('dashboard.faqs.index')->with('success', 'Question added.');
    }

    public function update(Request $request, Faq $faq)
    {
        $this->authorize('manage support requests');
        $faq->update($this->validated($request));

        return redirect()->route('dashboard.faqs.index')->with('success', 'Question updated.');
    }

    public function toggle(Faq $faq)
    {
        $this->authorize('manage support requests');
        $faq->update(['is_active' => !$faq->is_active]);

        return redirect()->back()->with('success', $faq->is_active ? 'Question shown in the apps.' : 'Question hidden from the apps.');
    }

    public function destroy(Faq $faq)
    {
        $this->authorize('manage support requests');
        $faq->delete();

        return redirect()->back()->with('success', 'Question deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'audience' => 'required|in:' . implode(',', array_keys(self::AUDIENCES)),
            'question' => 'required|string|max:255',
            'answer' => 'required|string|max:5000',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }
}

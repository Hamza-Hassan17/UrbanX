<?php

namespace App\Http\Controllers\Dashboard\Concerns;

use App\Models\AdminQueuePreset;
use Illuminate\Http\Request;

/**
 * Shared by the Rides and Delivery queue controllers (Phase 2 of the
 * workspace split). admin_queue_presets has no "which queue" column --
 * adding one would mean a migration just to avoid a string prefix, so
 * instead each queue's preset/last-filters names are namespaced in PHP
 * ("rides::My Preset", "delivery::__last__") to keep the two queues'
 * saved filters from colliding under the same name.
 */
trait HasQueuePresets
{
    private function loadQueuePresets(string $queueKey): array
    {
        $presets = AdminQueuePreset::where('user_id', auth()->id())
            ->where('name', 'like', "{$queueKey}::%")
            ->get(['name', 'filters'])
            ->keyBy(fn ($p) => substr($p->name, strlen($queueKey) + 2));

        $queuePresets = $presets->except('__last__')->map(fn ($p) => $p->filters)->all();
        $lastQueueFilters = $presets->get('__last__')?->filters ?? null;

        return [$queuePresets, $lastQueueFilters];
    }

    private function saveQueuePreset(Request $request, string $queueKey)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:60',
            'filters' => 'required|array',
        ]);

        $name = $queueKey . '::' . ($validated['name'] ?? '__last__');

        AdminQueuePreset::updateOrCreate(
            ['user_id' => $request->user()->id, 'name' => $name],
            ['filters' => $validated['filters']]
        );

        return response()->json(['saved' => $name]);
    }
}

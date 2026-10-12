<?php

namespace App\Services;

use App\Models\StaleJobAlert;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Batch 2 Part 3 -- stale job alerts. raise() is the only way an alert
 * gets created, so the no-duplicate-open-alert rule lives in one place.
 */
class StaleJobAlertService
{
    /**
     * Creates an open alert for (type, subject) unless one is already
     * open. Returns null when it was a no-op (already alerted).
     */
    public static function raise(string $type, string $subjectType, int $subjectId, string $workspace, string $message): ?StaleJobAlert
    {
        $exists = StaleJobAlert::where('type', $type)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return null;
        }

        $alert = StaleJobAlert::create([
            'type' => $type,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'workspace' => $workspace,
            'message' => $message,
        ]);

        Log::info('Stale job alert raised', ['alert_id' => $alert->id, 'type' => $type, 'subject_type' => $subjectType, 'subject_id' => $subjectId]);

        try {
            $recipients = User::role(['super admin', 'admin', 'operator', 'dispatcher'])->get();
            app('notificationService')->notifyUsers(
                $recipients,
                'Stale Job Alert',
                $message,
                'stale_job_alerts',
                $alert->id,
                'alert_details'
            );
        } catch (\Throwable $e) {
            Log::error('Stale job alert notification failed', ['alert_id' => $alert->id, 'error' => $e->getMessage()]);
        }

        return $alert;
    }

    public static function resolve(StaleJobAlert $alert, User $resolvedBy, ?string $note): void
    {
        $alert->status = 'resolved';
        $alert->resolved_by = $resolvedBy->id;
        $alert->resolution_note = $note;
        $alert->resolved_at = now();
        $alert->save();
    }
}

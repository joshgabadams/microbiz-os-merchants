<?php

namespace App\Services\Tessa;

use App\Models\TessaAlert;

/**
 * Shared alert-creation logic for all TESSA detection services.
 *
 * Reuses an existing OPEN alert for the same (alert_type, subject)
 * rather than creating a new one every detection run -- a teller with
 * an ongoing variance shouldn't accumulate a fresh alert every time the
 * scheduler fires. Once a human resolves an alert, a genuinely new
 * occurrence creates a fresh one rather than silently reopening the old
 * record (which would lose the resolution history).
 */
trait CreatesTessaAlerts
{
    protected function upsertOpenAlert(
        string $alertType,
        string $severity,
        string $subjectType,
        int $subjectId,
        string $title,
        string $description,
        array $metadata
    ): TessaAlert {
        $existing = TessaAlert::where('alert_type', $alertType)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->where('status', 'OPEN')
            ->first();

        if ($existing) {
            $existing->update([
                'severity' => $severity,
                'title' => $title,
                'description' => $description,
                'metadata' => $metadata,
                'detected_at' => now(),
            ]);

            return $existing->fresh();
        }

        return TessaAlert::create([
            'alert_type' => $alertType,
            'severity' => $severity,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'metadata' => $metadata,
            'status' => 'OPEN',
            'detected_at' => now(),
        ]);
    }
}

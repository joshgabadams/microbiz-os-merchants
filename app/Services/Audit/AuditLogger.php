<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Central audit logging service for MicroBiz cash operations.
 *
 * Call this from services (or from event listeners such as UpdateAuditTrail)
 * rather than writing to audit_logs directly, so redaction and actor
 * resolution stay consistent across vault, teller, and customer cash flows.
 */
class AuditLogger
{
    public function log(
        string $action,
        ?Model $subject = null,
        ?array $before = null,
        ?array $after = null,
        ?array $metadata = null,
        ?string $module = null,
    ): AuditLog {
        $actor = Auth::user();

        return AuditLog::create([
            'actor_id' => $actor?->id,
            'actor_label' => $actor?->email ?? 'system',

            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),

            'action' => $action,
            'module' => $module,

            'before' => $before ? $this->redact($before) : null,
            'after' => $after ? $this->redact($after) : null,
            'metadata' => $metadata,

            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'created_at' => now(),
        ]);
    }

    public function created(Model $subject, ?string $module = null, ?array $metadata = null): AuditLog
    {
        return $this->log(
            action: $this->actionName($subject, 'created'),
            subject: $subject,
            after: $subject->getAttributes(),
            metadata: $metadata,
            module: $module,
        );
    }

    public function updated(Model $subject, array $originalBefore, ?string $module = null, ?array $metadata = null): AuditLog
    {
        return $this->log(
            action: $this->actionName($subject, 'updated'),
            subject: $subject,
            before: $originalBefore,
            after: $subject->getChanges(),
            metadata: $metadata,
            module: $module,
        );
    }

    protected function actionName(Model $subject, string $verb): string
    {
        // TellerTransaction -> teller_transaction.created
        $base = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', class_basename($subject)));

        return "{$base}.{$verb}";
    }

    protected function redact(array $data): array
    {
        $redactFields = config('microbiz.audit.redact_fields', ['password', 'token', 'secret']);

        foreach ($redactFields as $field) {
            if (Arr::has($data, $field)) {
                Arr::set($data, $field, '***REDACTED***');
            }
        }

        return $data;
    }
}

<?php

namespace App\Services;

use App\Models\AuditLog;

class Audit
{
    public static function record(string $action, string $entity, ?int $id = null, ?array $before = null, ?array $after = null, ?string $reason = null, ?int $actor = null): void
    {
        AuditLog::create([
            'user_id' => $actor ?? auth()->id(), 'action' => $action,
            'entity_type' => $entity, 'entity_id' => $id,
            'before' => $before, 'after' => $after, 'reason' => $reason,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'created_at' => now(),
        ]);
    }
}

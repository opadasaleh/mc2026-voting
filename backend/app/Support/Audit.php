<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Event;

/**
 * Records sensitive admin actions in the append-only audit log.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Event $event = null, array $meta = []): AuditLog
    {
        return AuditLog::create([
            'user_id' => auth()->id(),
            'event_id' => $event?->getKey(),
            'action' => $action,
            'meta' => $meta,
            'ip' => request()->ip(),
        ]);
    }
}

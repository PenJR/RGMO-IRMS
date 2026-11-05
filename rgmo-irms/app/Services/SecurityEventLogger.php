<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class SecurityEventLogger
{
    /** Record a safe, server-side security event. Request bodies and keystrokes are never logged. */
    public function record(string $event, Request $request, ?User $user = null, array $context = []): void
    {
        $payload = array_filter([
            'event' => $event,
            'user_id' => $user?->id,
            'role' => $user?->normalizedRole(),
            'ip_address' => $request->ip(),
            'method' => $request->method(),
            'route' => $request->route()?->getName(),
            'path' => $request->path(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
        ] + $context, static fn ($value) => $value !== null && $value !== '');

        AuditLog::create([
            'user_id' => $user?->id,
            'action' => $event,
            'module' => 'security',
            'ip_address' => $request->ip(),
            'details' => $payload,
        ]);

        if (! config('logging.security_syslog_enabled')) {
            return;
        }

        try {
            Log::channel('security_syslog')->notice('security_event', $payload);
        } catch (Throwable $exception) {
            // Monitoring must never make the application unavailable when its collector is offline.
            Log::warning('Security syslog forwarding failed.', ['event' => $event, 'error' => $exception->getMessage()]);
        }
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use App\Services\SecurityEventLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    /**
     * Handle an incoming request - Log all user activities
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && ! $this->shouldSkip($request)) {
            // Log user activity
            UserActivityLog::create([
                'user_id' => auth()->id(),
                'activity' => $request->method().' '.$request->path(),
                'ip_address' => $request->ip(),
                'context' => [
                    'url' => $request->url(),
                    'method' => $request->method(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);
        }

        $response = $next($request);

        if (auth()->check() && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            app(SecurityEventLogger::class)->record('http_activity', $request, auth()->user(), [
                'status' => $response->getStatusCode(),
            ]);
        }

        return $response;
    }

    /**
     * Avoid filling the activity table with background polling requests.
     */
    private function shouldSkip(Request $request): bool
    {
        return $request->routeIs(
            'notifications.unread-count',
            'dashboard.data',
            'dashboard.health.data',
        ) || $request->is(
            'notifications/unread-count',
            'dashboard/data',
            'dashboard/health/data',
            'api/notifications/unread-count',
            'api/dashboard/health',
        );
    }
}

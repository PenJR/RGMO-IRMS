<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\UserActivityLog;
use App\Models\AuditLog;

class LogUserActivity
{
    /**
     * Handle an incoming request - Log all user activities
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check()) {
            // Log user activity
            UserActivityLog::create([
                'user_id' => auth()->id(),
                'activity' => $request->method() . ' ' . $request->path(),
                'ip_address' => $request->ip(),
                'context' => [
                    'url' => $request->url(),
                    'method' => $request->method(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);
        }

        return $next($request);
    }
}

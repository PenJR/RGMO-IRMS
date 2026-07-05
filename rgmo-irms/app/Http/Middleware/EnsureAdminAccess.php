<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminAccess
{
    /**
     * Handle an incoming request - Admin only access
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check() || !auth()->user()->isAdmin()) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => auth()->check() ? 'Forbidden.' : 'Unauthenticated.',
                ], auth()->check() ? 403 : 401);
            }

            return response()->view('errors.403', [], 403);
        }

        return $next($request);
    }
}

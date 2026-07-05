<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PermissionMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        if (! auth()->check()) {
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->isSuspended()) {
            auth()->logout();
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Your account has been suspended. Contact administrator.'], 403);
            }

            return redirect()->route('login')->with('error', 'Your account has been suspended. Contact administrator.');
        }

        if (! $user->isActive()) {
            auth()->logout();
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Your account is inactive. Contact administrator.'], 403);
            }

            return redirect()->route('login')->with('error', 'Your account is inactive. Contact administrator.');
        }

        if ($user->isAccountLocked()) {
            auth()->logout();
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Your account is temporarily locked due to failed login attempts.'], 403);
            }

            return redirect()->route('login')->with('error', 'Your account is temporarily locked due to failed login attempts.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        if ($this->shouldReturnJson($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->view('errors.403', [], 403);
    }

    private function shouldReturnJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}

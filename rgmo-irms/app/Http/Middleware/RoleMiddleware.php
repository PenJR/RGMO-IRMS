<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if user is suspended
        if ($user->isSuspended()) {
            auth()->logout();
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Your account has been suspended. Contact administrator.'], 403);
            }

            return redirect()->route('login')->with('error', 'Your account has been suspended. Contact administrator.');
        }

        // Check if user is not active
        if (!$user->isActive()) {
            auth()->logout();
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Your account is inactive. Contact administrator.'], 403);
            }

            return redirect()->route('login')->with('error', 'Your account is inactive. Contact administrator.');
        }

        // Check if user account is locked
        if ($user->isAccountLocked()) {
            auth()->logout();
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Your account is temporarily locked due to failed login attempts.'], 403);
            }

            return redirect()->route('login')->with('error', 'Your account is temporarily locked due to failed login attempts.');
        }

        // Check if user has one of the required roles
        if (! $user instanceof User || ! $user->hasRole($roles)) {
            if ($this->shouldReturnJson($request)) {
                return response()->json(['message' => 'Forbidden.'], 403);
            }

            return response()->view('errors.403', [], 403);
        }

        return $next($request);
    }

    private function shouldReturnJson(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }
}

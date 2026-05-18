<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
            return redirect()->route('login');
        }

        $user = auth()->user();

        // Check if user is suspended
        if ($user->isSuspended()) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'Your account has been suspended. Contact administrator.');
        }

        // Check if user is not active
        if (!$user->isActive()) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'Your account is inactive. Contact administrator.');
        }

        // Check if user account is locked
        if ($user->isAccountLocked()) {
            auth()->logout();
            return redirect()->route('login')->with('error', 'Your account is temporarily locked due to failed login attempts.');
        }

        // Check if user has one of the required roles
        if (!in_array($user->role, $roles)) {
            return response()->view('errors.403', [], 403);
        }

        return $next($request);
    }
}

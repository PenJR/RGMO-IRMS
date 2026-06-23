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
            return redirect()->route('login');
        }

        $user = auth()->user();

        if ($user->isSuspended()) {
            auth()->logout();

            return redirect()->route('login')->with('error', 'Your account has been suspended. Contact administrator.');
        }

        if (! $user->isActive()) {
            auth()->logout();

            return redirect()->route('login')->with('error', 'Your account is inactive. Contact administrator.');
        }

        if ($user->isAccountLocked()) {
            auth()->logout();

            return redirect()->route('login')->with('error', 'Your account is temporarily locked due to failed login attempts.');
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return $next($request);
            }
        }

        return response()->view('errors.403', [], 403);
    }
}
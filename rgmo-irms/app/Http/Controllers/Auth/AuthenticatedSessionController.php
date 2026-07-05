<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoginHistory;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $user = auth()->user();

        // If user has 2FA enabled, pause full login and require code verification
        if ($user && $user->two_factor_enabled) {
            auth()->logout();
            $request->session()->put('2fa:user_id', $user->id);
            return redirect()->route('2fa.verify');
        }

        $request->session()->regenerate();

        $loginAt = now();

        LoginHistory::create([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'login_at' => $loginAt,
        ]);

        $this->notificationService->notifyAdminLoggedIn($user, [
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'login_at' => $loginAt->toDateTimeString(),
        ]);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($userId = Auth::id()) {
            $loginHistory = LoginHistory::where('user_id', $userId)
                ->openSession()
                ->latest('login_at')
                ->first();

            $loginHistory?->update(['logout_at' => now()]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

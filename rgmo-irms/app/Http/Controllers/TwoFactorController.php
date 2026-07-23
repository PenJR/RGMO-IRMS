<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TwoFactorController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Show the 2FA enablement details, generating a secret if needed.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function showEnable(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (! $user->two_factor_secret) {
            $secret = $this->twoFactor->generateSecret();
            $user->update(['two_factor_secret' => $secret, 'two_factor_enabled' => false]);
        } else {
            $secret = $user->two_factor_secret;
        }

        $uri = $this->twoFactor->getProvisioningUri($user->email, $secret);

        return response()->json(['secret' => $secret, 'otpauth_url' => $uri]);
    }

    /**
     * Confirm 2FA setup by verifying a code provided by the user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user() ?? auth()->user();
        if (! $user) abort(403);

        $ok = $this->twoFactor->verifyCode($user->two_factor_secret, $request->input('code'));
        if (! $ok) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        $user->update(['two_factor_enabled' => true]);
        return response()->json(['message' => 'Two-factor authentication enabled']);
    }

    /**
     * Disable 2FA for the authenticated user, requires password validation.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (! $user) abort(403);

        if (! \Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid password'], 422);
        }

        $user->update(['two_factor_enabled' => false, 'two_factor_secret' => null]);
        return response()->json(['message' => 'Two-factor authentication disabled']);
    }

    /**
     * Show the 2FA verification challenge during login.
     *
     * @param Request $request
     * @return \Illuminate\View\View|\Illuminate\Http\JsonResponse
     */
    public function showVerify(Request $request)
    {
        if ($request->wantsJson()) {
            return response()->json(['2fa_required' => true]);
        }

        return view('auth.2fa-verify');
    }

    /**
     * Verify the 2FA code during a login attempt and authenticate the user.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $pendingUserId = null;
        if ($request->hasSession()) {
            $pendingUserId = $request->session()->get('2fa:user_id');
        }

        if (! $pendingUserId && $request->input('two_factor_challenge')) {
            $challengeKey = '2fa:challenge:' . hash('sha256', (string) $request->input('two_factor_challenge'));
            $pendingUserId = Cache::pull($challengeKey);
        }

        $user = null;
        if ($pendingUserId) {
            $user = User::find($pendingUserId);
        }
        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Invalid or expired two-factor challenge.'], 422);
            }
            return redirect()->route('login')->withErrors(['message' => 'Invalid user.']);
        }

        $code = $request->input('code');
        if (! $this->twoFactor->verifyCode($user->two_factor_secret, $code)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The provided two-factor code is invalid.'], 422);
            }
            return back()->withErrors(['code' => 'The provided two-factor code is invalid.']);
        }

        // Mark 2FA as confirmed
        $user->update(['two_factor_enabled' => true]);

        // Finalize login (session may not exist for API requests)
        Auth::loginUsingId($user->id);
        if ($request->hasSession()) {
            $request->session()->forget('2fa:user_id');
            $request->session()->regenerate();
        }

        $loginAt = now();
        $user->update(['last_login_at' => $loginAt]);

        LoginHistory::create([
            'user_id' => $user->id,
            'user_role' => $user->role,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'login_at' => $loginAt,
        ]);

        $this->notificationService->notifyUserLoggedIn($user, [
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'login_at' => $loginAt->toDateTimeString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Authenticated']);
        }

        return redirect()->route('dashboard');
    }
}

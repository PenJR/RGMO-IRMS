<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

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
     * @return JsonResponse
     */
    public function showEnable(Request $request)
    {
        $validated = $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if ($user->two_factor_enabled) {
            return response()->json(['message' => 'Two-factor authentication is already enabled.'], 409);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'The provided password is incorrect.'], 422);
        }

        // Rotate any unfinished setup secret so an exposed or abandoned key cannot be reused.
        $secret = $this->twoFactor->generateSecret();
        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_enabled' => false,
            'two_factor_recovery_codes' => null,
            'two_factor_last_used_step' => null,
        ]);

        $uri = $this->twoFactor->getProvisioningUri($user->email, $secret);

        return response()->json(['secret' => $secret, 'otpauth_url' => $uri]);
    }

    /**
     * Reveal the current setup key after password confirmation.
     */
    public function revealSecret(Request $request)
    {
        $validated = $request->validate(['password' => 'required|string']);
        $user = $request->user();

        if (! $user || $user->two_factor_enabled || ! $user->two_factor_secret) {
            return response()->json(['message' => 'No pending two-factor setup was found.'], 422);
        }

        if (! Hash::check($validated['password'], $user->password)) {
            return response()->json(['message' => 'The provided password is incorrect.'], 422);
        }

        return response()->json([
            'secret' => $user->two_factor_secret,
            'expires_in' => 600,
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Confirm 2FA setup by verifying a code provided by the user.
     *
     * @return JsonResponse
     */
    public function confirm(Request $request)
    {
        $request->validate(['code' => 'required|string']);
        $user = $request->user() ?? auth()->user();
        if (! $user) {
            abort(403);
        }

        $matchedStep = $this->twoFactor->matchedTimeSlice($user->two_factor_secret, $request->input('code'));
        if ($matchedStep === null || ! $this->claimTimeSlice($user, $matchedStep)) {
            return response()->json(['message' => 'Invalid code'], 422);
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->update([
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => array_map(fn (string $code) => Hash::make($code), $recoveryCodes),
        ]);

        return response()->json([
            'message' => 'Two-factor authentication enabled',
            'recovery_codes' => $recoveryCodes,
        ])->header('Cache-Control', 'no-store, private');
    }

    /**
     * Disable 2FA for the authenticated user, requires password validation.
     *
     * @return JsonResponse
     */
    public function disable(Request $request)
    {
        $request->validate(['password' => 'required|string']);
        $user = $request->user();
        if (! $user) {
            abort(403);
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Invalid password'], 422);
        }

        $user->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_used_step' => null,
        ]);

        return response()->json(['message' => 'Two-factor authentication disabled']);
    }

    /**
     * Show the 2FA verification challenge during login.
     *
     * @return View|JsonResponse
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
     * @return RedirectResponse|JsonResponse
     */
    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string|max:64']);

        $pendingUserId = null;
        if ($request->hasSession()) {
            $pendingUserId = $request->session()->get('2fa:user_id');
        }

        if (! $pendingUserId && $request->input('two_factor_challenge')) {
            $challengeKey = '2fa:challenge:'.hash('sha256', (string) $request->input('two_factor_challenge'));
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
        $matchedStep = $this->twoFactor->matchedTimeSlice($user->two_factor_secret, $code);
        $verified = $matchedStep !== null
            ? $this->claimTimeSlice($user, $matchedStep)
            : $this->consumeRecoveryCode($user, $code);

        if (! $verified) {
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

    /**
     * Atomically prevent reuse of a TOTP code from the same or an older time slice.
     */
    private function claimTimeSlice(User $user, int $timeSlice): bool
    {
        return User::query()
            ->whereKey($user->id)
            ->where(function ($query) use ($timeSlice) {
                $query->whereNull('two_factor_last_used_step')
                    ->orWhere('two_factor_last_used_step', '<', $timeSlice);
            })
            ->update(['two_factor_last_used_step' => $timeSlice]) === 1;
    }

    /**
     * Generate one-time recovery codes. Only their hashes are persisted.
     *
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::upper(Str::random(5).'-'.Str::random(5)))
            ->all();
    }

    /**
     * Consume a recovery code exactly once.
     */
    private function consumeRecoveryCode(User $user, string $candidate): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hash) {
            if (! Hash::check(Str::upper(trim($candidate)), $hash)) {
                continue;
            }

            unset($codes[$index]);
            $user->update(['two_factor_recovery_codes' => array_values($codes)]);

            return true;
        }

        return false;
    }
}

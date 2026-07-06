<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\RmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly RmsService $service) {}

    /**
     * Handle an incoming authentication request for the application.
     * Supports standard email/password login and handles 2FA redirection if enabled.
     *
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function loginUser(LoginRequest $request)
    {
        $request->ensureIsNotRateLimited();

        $credentials = $request->validated();

        if (! $this->service->loginUser($credentials)) {
            RateLimiter::hit($request->throttleKey());

            return response()->json(['message' => 'Invalid credentials or account locked'], 422);
        }

        RateLimiter::clear($request->throttleKey());
        $user = auth()->user();

        // If the user has 2FA enabled, don't finalize login yet — require verification
        if ($user && $user->two_factor_enabled) {
            // remember pending 2FA (session if available) and log the user out until code is verified
            auth()->logout();
            if ($request->hasSession()) {
                $request->session()->put('2fa:user_id', $user->id);
            }

            $challenge = Str::random(64);
            Cache::put('2fa:challenge:' . hash('sha256', $challenge), $user->id, now()->addMinutes(5));

            return response()->json([
                '2fa_required' => true,
                'two_factor_challenge' => $challenge,
            ], 202);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json($this->service->getAuthenticatedUser());
    }

    /**
     * Log the current user out of the application and invalidate their session.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logoutUser(Request $request)
    {
        $this->service->logoutUser();
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
        return response()->json(['message' => 'Logged out']);
    }

    /**
     * Create a new user account (restricted to Administrators).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function registerUser(Request $request)
    {
        abort_unless(Auth::user()?->hasPermission('manage-users'), 403);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => 'required|in:admin,staff,project_manager,rgmo_head',
        ]);
        $user = $this->service->registerUser($data);
        return response()->json($user, 201);
    }

    /**
     * Initiate a password reset process for the given email address.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        return response()->json(['status' => $this->service->resetPassword($request->all())]);
    }

    /**
     * Change the password of the currently authenticated user.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'new_password' => ['required', 'string', 'confirmed', Password::defaults()],
        ]);
        $this->service->changePassword(Auth::id(), $data['current_password'], $data['new_password']);
        return response()->json(['message' => 'Password changed']);
    }

    /**
     * Get the details of the currently authenticated user.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAuthenticatedUser()
    {
        return response()->json($this->service->getAuthenticatedUser());
    }
}

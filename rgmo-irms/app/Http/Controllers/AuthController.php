<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Services\RmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function __construct(private readonly RmsService $service) {}

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
            return response()->json(['2fa_required' => true, 'user_id' => $user->id], 202);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json($this->service->getAuthenticatedUser());
    }

    public function logoutUser(Request $request)
    {
        $this->service->logoutUser();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['message' => 'Logged out']);
    }

    public function registerUser(Request $request)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8', 'role' => 'required|in:admin,staff,field_personnel']);
        $user = $this->service->registerUser($data);
        return response()->json($user, 201);
    }

    public function resetPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        return response()->json(['status' => $this->service->resetPassword($request->all())]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string', 'new_password' => 'required|string|min:8|confirmed']);
        $this->service->changePassword(Auth::id(), $data['current_password'], $data['new_password']);
        return response()->json(['message' => 'Password changed']);
    }

    public function getAuthenticatedUser()
    {
        return response()->json($this->service->getAuthenticatedUser());
    }
}

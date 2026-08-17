<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the authenticated user's profile settings form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'sessionControls' => $this->sessionControls($request),
        ]);
    }

    /**
     * Update the authenticated user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Save the authenticated user's preferred top-level sidebar order.
     */
    public function updateSidebarOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'array', 'max:'.count(User::SIDEBAR_ITEMS)],
            'order.*' => ['required', 'string', 'distinct', Rule::in(User::SIDEBAR_ITEMS)],
        ]);

        $request->user()->update(['sidebar_order' => array_values($validated['order'])]);

        return response()->json(['message' => 'Sidebar order saved.']);
    }

    /**
     * Restore the authenticated user's sidebar to the default module order.
     */
    public function resetSidebarOrder(Request $request): RedirectResponse
    {
        $request->user()->update(['sidebar_order' => null]);

        return Redirect::route('profile.edit')->with('status', 'sidebar-order-reset');
    }

    /**
     * Revoke one of the authenticated user's other browser sessions.
     */
    public function destroySession(Request $request, string $sessionId): RedirectResponse
    {
        abort_if($sessionId === $request->session()->getId(), 422, 'Use Sign out to end your current session.');
        abort_unless($this->databaseSessionsEnabled(), 409, 'Session management requires the database session driver.');

        $deleted = DB::table((string) config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', $request->user()->id)
            ->delete();

        abort_if($deleted === 0, 404);

        return Redirect::route('profile.edit')->with('status', 'session-revoked');
    }

    /**
     * Revoke every browser session except the one making this request.
     */
    public function destroyOtherSessions(Request $request): RedirectResponse
    {
        abort_unless($this->databaseSessionsEnabled(), 409, 'Session management requires the database session driver.');

        DB::table((string) config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        return Redirect::route('profile.edit')->with('status', 'other-sessions-revoked');
    }

    /** @return array{enabled: bool, lifetime: int, expire_on_close: bool, http_only: bool, secure: bool, same_site: string, sessions: Collection<int, array<string, mixed>>} */
    private function sessionControls(Request $request): array
    {
        $enabled = $this->databaseSessionsEnabled();
        $sessions = collect();

        if ($enabled) {
            $sessions = DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $request->user()->id)
                ->orderByDesc('last_activity')
                ->get()
                ->map(fn (object $session): array => [
                    'id' => $session->id,
                    'current' => hash_equals((string) $session->id, $request->session()->getId()),
                    'device' => $this->deviceName((string) $session->user_agent),
                    'ip_address' => $session->ip_address ?: 'Unknown IP',
                    'last_active' => Carbon::createFromTimestamp((int) $session->last_activity),
                ]);
        }

        if (! $sessions->contains('current', true)) {
            $sessions->prepend([
                'id' => $request->session()->getId(),
                'current' => true,
                'device' => $this->deviceName((string) $request->userAgent()),
                'ip_address' => $request->ip(),
                'last_active' => now(),
            ]);
        }

        return [
            'enabled' => $enabled,
            'lifetime' => max(1, (int) config('session.lifetime', 120)),
            'expire_on_close' => (bool) config('session.expire_on_close'),
            'http_only' => (bool) config('session.http_only'),
            'secure' => (bool) config('session.secure'),
            'same_site' => ucfirst((string) config('session.same_site', 'lax')),
            'sessions' => $sessions,
        ];
    }

    private function databaseSessionsEnabled(): bool
    {
        return config('session.driver') === 'database';
    }

    private function deviceName(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Browser',
        };
        $device = match (true) {
            preg_match('/iPhone|iPad/i', $userAgent) === 1 => 'iOS',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "{$browser} on {$device}";
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    /**
     * Display a paginated listing of users with search, role, and status filtering.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query();

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%$search%")
                ->orWhere('email', 'like', "%$search%");
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $users = $query->paginate(15);

        return view('admin.users.index', ['users' => $users]);
    }

    /**
     * Display a paginated listing of system-wide login logs with user and date filtering.
     *
     * @param Request $request
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function loginLogs(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = LoginHistory::query()->with('user');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('user', function ($userQuery) use ($request) {
                $userQuery->where('role', $request->input('role'));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('login_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('login_at', '<=', $request->input('date_to'));
        }

        $loginLogs = $query->latest('login_at')->paginate(25)->withQueryString();

        return view('admin.login-logs.index', ['loginLogs' => $loginLogs]);
    }

    /**
     * Show the creation form for a new user account.
     *
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function create()
    {
        $this->authorize('create', User::class);

        return view('admin.users.create');
    }

    /**
     * Store a newly created user account in the database.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:admin,staff,project_manager,rgmo_head',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'],
        ]);

        return redirect()->route('admin.users.show', $user)->with('success', 'User created successfully.');
    }

    /**
     * Display details of a specific user including their recent activities and audit logs.
     *
     * @param User $user
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show(User $user)
    {
        $this->authorize('view', $user);

        $activityLogs = $user->activityLogs()->latest()->limit(20)->get();
        $auditLogs = $user->auditLogs()->latest()->limit(20)->get();

        return view('admin.users.show', [
            'user' => $user,
            'activityLogs' => $activityLogs,
            'auditLogs' => $auditLogs,
        ]);
    }

    /**
     * Show the edit form for an existing user account.
     *
     * @param User $user
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', ['user' => $user]);
    }

    /**
     * Update an existing user account's profile information and status.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,staff,project_manager,rgmo_head',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated successfully.');
    }

    /**
     * Delete a specific user account from the system.
     *
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Administrative override to reset a specific user's login password.
     *
     * @param Request $request
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function resetPassword(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update(['password' => bcrypt($validated['password'])]);

        return redirect()->route('admin.users.show', $user)->with('success', 'Password reset successfully.');
    }

    /**
     * Start a session impersonating another user for troubleshooting or support purposes.
     *
     * @param User $user
     * @return \Illuminate\Http\RedirectResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function impersonate(User $user)
    {
        $this->authorize('impersonate', $user);

        session(['impersonate_user' => $user->id, 'impersonate_original_user' => auth()->id()]);

        return redirect()->route('dashboard')->with('success', 'You are now impersonating ' . $user->name);
    }

    /**
     * End the current impersonation session and return to the original administrator account.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function stopImpersonate()
    {
        $originalUserId = session('impersonate_original_user');
        session()->forget(['impersonate_user', 'impersonate_original_user']);

        return redirect()->route('admin.users.index')->with('success', 'Impersonation stopped.');
    }

    /**
     * Retrieve the specific login history for a single user account.
     *
     * @param User $user
     * @return \Illuminate\View\View
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function loginHistory(User $user)
    {
        $this->authorize('view', $user);

        $loginHistory = $user->loginHistories()
            ->latest('login_at')
            ->paginate(20);

        return view('admin.users.login-history', [
            'user' => $user,
            'loginHistory' => $loginHistory,
        ]);
    }
}

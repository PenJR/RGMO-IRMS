<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class AdminUserController extends Controller
{
    /**
     * Display a listing of users
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
     * Show the form for creating a new user
     */
    public function create()
    {
        $this->authorize('create', User::class);

        return view('admin.users.create');
    }

    /**
     * Store a newly created user in storage
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:admin,staff,field_personnel',
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
     * Display the specified user
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
     * Show the form for editing the specified user
     */
    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', ['user' => $user]);
    }

    /**
     * Update the specified user in storage
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'role' => 'required|in:admin,staff,field_personnel',
            'status' => 'required|in:active,inactive,suspended',
        ]);

        $user->update($validated);

        return redirect()->route('admin.users.show', $user)->with('success', 'User updated successfully.');
    }

    /**
     * Delete the specified user
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    /**
     * Reset user password
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
     * Impersonate a user (admin login as user)
     */
    public function impersonate(User $user)
    {
        $this->authorize('impersonate', $user);

        session(['impersonate_user' => $user->id, 'impersonate_original_user' => auth()->id()]);

        return redirect()->route('dashboard')->with('success', 'You are now impersonating ' . $user->name);
    }

    /**
     * Stop impersonating
     */
    public function stopImpersonate()
    {
        $originalUserId = session('impersonate_original_user');
        session()->forget(['impersonate_user', 'impersonate_original_user']);

        return redirect()->route('admin.users.index')->with('success', 'Impersonation stopped.');
    }

    /**
     * Get user's login history
     */
    public function loginHistory(User $user)
    {
        $this->authorize('view', $user);

        $loginHistory = $user->activityLogs()
            ->where('activity', 'like', '%login%')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('admin.users.login-history', [
            'user' => $user,
            'loginHistory' => $loginHistory,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly UserService $service) {}

    /**
     * Create a new system user with the specified roles and permissions.
     *
     * @return JsonResponse
     */
    public function createUser(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => 'required|in:'.implode(',', User::availableRoles()),
        ]);

        return response()->json($this->service->create($validated), 201);
    }

    /**
     * Update the profile information and access status of an existing user.
     *
     * @return JsonResponse
     */
    public function updateUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|lowercase|email|max:255|unique:users,email,'.$id,
            'role' => 'sometimes|in:'.implode(',', User::availableRoles()),
            'status' => 'sometimes|in:active,inactive,suspended',
        ]);

        return response()->json($this->service->update($user, $validated, $request->user()->id));
    }

    /**
     * Remove the specified user from the system.
     *
     * @return JsonResponse
     */
    public function deleteUser(int $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('delete', $user);

        $this->service->delete($user, auth()->id());

        return response()->json(['message' => 'User deleted']);
    }

    /**
     * Retrieve a listing of all registered users.
     *
     * @return JsonResponse
     */
    public function getAllUsers()
    {
        $this->authorize('viewAny', User::class);

        return response()->json($this->service->getAllUsers());
    }

    /**
     * Retrieve details for a specific user by their unique ID.
     *
     * @return JsonResponse
     */
    public function getUserById(int $id)
    {
        $user = $this->service->getUserById($id);
        $this->authorize('view', $user);

        return response()->json($user);
    }

    /**
     * Update the system role for a specific user.
     *
     * @return JsonResponse
     */
    public function assignRole(Request $request, int $userId)
    {
        $this->authorize('assignRole', User::class);
        $user = User::findOrFail($userId);

        $validated = $request->validate([
            'role' => 'required|in:'.implode(',', User::availableRoles()),
        ]);

        return response()->json($this->service->update($user, ['role' => $validated['role']], $request->user()->id));
    }

    /**
     * Suspend user access by deactivating their account.
     *
     * @return JsonResponse
     */
    public function deactivateUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        return response()->json($this->service->update($user, ['status' => User::STATUS_INACTIVE], $request->user()->id));
    }

    /**
     * Restore user access by activating their account.
     *
     * @return JsonResponse
     */
    public function activateUser(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);

        return response()->json($this->service->update($user, ['status' => User::STATUS_ACTIVE], $request->user()->id));
    }

    /**
     * Retrieve the audit trail of actions performed by a specific user.
     *
     * @return JsonResponse
     */
    public function getUserActivityLogs(int $userId)
    {
        $user = User::findOrFail($userId);
        $this->authorize('view', $user);

        return response()->json($this->service->getUserActivityLogs($userId));
    }
}

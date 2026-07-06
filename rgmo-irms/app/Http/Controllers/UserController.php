<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RmsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * Create a new instance.
     */
    public function __construct(private readonly RmsService $service) {}

    /**
     * Create a new system user with the specified roles and permissions.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:users,email',
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'role' => 'required|in:' . implode(',', User::availableRoles()),
        ]);

        return response()->json($this->service->createUser($validated), 201);
    }

    /**
     * Update the profile information and access status of an existing user.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateUser(Request $request, int $id)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|lowercase|email|max:255|unique:users,email,' . $id,
            'role' => 'sometimes|in:' . implode(',', User::availableRoles()),
            'status' => 'sometimes|in:active,inactive,suspended',
            'is_active' => 'sometimes|boolean',
        ]);

        return response()->json($this->service->updateUser($id, $validated));
    }

    /**
     * Remove the specified user from the system.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteUser(int $id)
    {
        $this->service->deleteUser($id);
        return response()->json(['message' => 'User deleted']);
    }

    /**
     * Retrieve a listing of all registered users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllUsers()
    {
        return response()->json($this->service->getAllUsers());
    }

    /**
     * Retrieve details for a specific user by their unique ID.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserById(int $id)
    {
        return response()->json($this->service->getUserById($id));
    }

    /**
     * Update the system role for a specific user.
     *
     * @param Request $request
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignRole(Request $request, int $userId)
    {
        $validated = $request->validate([
            'role' => 'required|in:' . implode(',', User::availableRoles()),
        ]);

        return response()->json($this->service->assignRole($userId, $validated['role']));
    }

    /**
     * Suspend user access by deactivating their account.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function deactivateUser(int $id)
    {
        return response()->json($this->service->deactivateUser($id));
    }

    /**
     * Restore user access by activating their account.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activateUser(int $id)
    {
        return response()->json($this->service->activateUser($id));
    }

    /**
     * Retrieve the audit trail of actions performed by a specific user.
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserActivityLogs(int $userId)
    {
        return response()->json($this->service->getUserActivityLogs($userId));
    }
}

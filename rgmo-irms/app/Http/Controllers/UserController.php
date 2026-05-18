<?php

namespace App\Http\Controllers;

use App\Services\RmsService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(private readonly RmsService $service) {}

    public function createUser(Request $request) { return response()->json($this->service->createUser($request->validate(['name' => 'required|string|max:255', 'email' => 'required|email|unique:users,email', 'password' => 'required|string|min:8', 'role' => 'required|in:admin,staff,field_personnel'])), 201); }
    public function updateUser(Request $request, int $id) { return response()->json($this->service->updateUser($id, $request->validate(['name' => 'sometimes|string|max:255', 'email' => 'sometimes|email|unique:users,email,' . $id, 'role' => 'sometimes|in:admin,staff,field_personnel', 'is_active' => 'sometimes|boolean']))); }
    public function deleteUser(int $id) { $this->service->deleteUser($id); return response()->json(['message' => 'User deleted']); }
    public function getAllUsers() { return response()->json($this->service->getAllUsers()); }
    public function getUserById(int $id) { return response()->json($this->service->getUserById($id)); }
    public function assignRole(Request $request, int $userId) { return response()->json($this->service->assignRole($userId, $request->validate(['role' => 'required|in:admin,staff,field_personnel'])['role'])); }
    public function deactivateUser(int $id) { return response()->json($this->service->deactivateUser($id)); }
    public function activateUser(int $id) { return response()->json($this->service->activateUser($id)); }
    public function getUserActivityLogs(int $userId) { return response()->json($this->service->getUserActivityLogs($userId)); }
}

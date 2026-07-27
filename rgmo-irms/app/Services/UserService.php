<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function update(User $user, array $data, int $actorId): User
    {
        $this->guardLifecycleChange($user, $data, $actorId);
        $oldValues = $user->toArray();
        $accessChanged = (array_key_exists('role', $data) && $data['role'] !== $user->role)
            || (array_key_exists('status', $data) && $data['status'] !== $user->status);

        $user->update($data);

        if ($accessChanged) {
            $this->invalidateSessions($user);
        }

        AuditLog::log($actorId, 'update', 'user_management', User::class, $user->id, $oldValues, $user->fresh()->toArray());

        return $user->fresh();
    }

    public function delete(User $user, int $actorId): void
    {
        if ($user->id === $actorId) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        $this->guardLastActiveAdministrator($user, null, null);
        $oldValues = $user->toArray();
        $this->invalidateSessions($user);
        $user->delete();
        AuditLog::log($actorId, 'delete', 'user_management', User::class, $user->id, $oldValues);
    }

    public function resetPassword(User $user, string $password, int $actorId): User
    {
        $user->update(['password' => $password, 'remember_token' => Str::random(60)]);
        $this->invalidateSessions($user);
        AuditLog::log(
            $actorId,
            'password_reset',
            'user_management',
            User::class,
            $user->id,
            null,
            ['password_reset_at' => now()->toDateTimeString()]
        );

        return $user->fresh();
    }

    public function getAllUsers()
    {
        return User::query()->paginate(20);
    }

    public function getUserById(int $id): User
    {
        return User::findOrFail($id);
    }

    public function getUserActivityLogs(int $userId)
    {
        return UserActivityLog::where('user_id', $userId)->latest()->get();
    }

    private function guardLifecycleChange(User $user, array $data, int $actorId): void
    {
        $newRole = $data['role'] ?? $user->role;
        $newStatus = $data['status'] ?? $user->status;

        if ($user->id === $actorId && ($newRole !== $user->role || $newStatus !== User::STATUS_ACTIVE)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot change your own role or deactivate your own account.',
            ]);
        }

        $this->guardLastActiveAdministrator($user, $newRole, $newStatus);
    }

    private function guardLastActiveAdministrator(User $user, ?string $newRole, ?string $newStatus): void
    {
        if (! $user->isAdmin() || ! $user->isActive()) {
            return;
        }

        $removesActiveAdmin = $newRole === null
            || $newStatus === null
            || $newRole !== User::ROLE_ADMIN
            || $newStatus !== User::STATUS_ACTIVE;

        if ($removesActiveAdmin && User::admin()->active()->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'The final active administrator cannot be deleted, demoted, or deactivated.',
            ]);
        }
    }

    private function invalidateSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
    }
}

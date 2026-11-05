<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_records_a_safe_security_event_without_the_password(): void
    {
        $user = User::factory()->create([
            'email' => 'monitoring@example.test',
            'password' => Hash::make('CorrectPassword123!'),
            'role' => User::ROLE_STAFF,
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'IncorrectPassword123!'])
            ->assertSessionHasErrors('email');

        $event = AuditLog::where('action', 'login_failed')->where('module', 'security')->firstOrFail();
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame(422, $event->details['status']);
        $this->assertArrayNotHasKey('password', $event->details);
    }

    public function test_denied_access_records_the_required_permission_without_request_input(): void
    {
        $staff = User::factory()->create(['role' => User::ROLE_STAFF, 'status' => User::STATUS_ACTIVE]);

        $this->actingAs($staff)->get(route('admin.users.index'))->assertForbidden();

        $event = AuditLog::where('action', 'access_denied')->where('module', 'security')->firstOrFail();
        $this->assertSame($staff->id, $event->user_id);
        $this->assertContains('manage-users', $event->details['required_permissions']);
        $this->assertArrayNotHasKey('input', $event->details);
    }
}

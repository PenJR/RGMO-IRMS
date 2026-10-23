<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that login screen can be rendered.
     */
    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    /**
     * Verify that users can authenticate using the login screen.
     */
    #[DataProvider('loginRoles')]
    public function test_users_can_authenticate_using_the_login_screen(string $role): void
    {
        $user = User::factory()->create([
            'role' => $role,
            'status' => User::STATUS_ACTIVE,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
        $this->followRedirects($response)->assertOk();
    }

    public static function loginRoles(): array
    {
        return [
            'admin' => [User::ROLE_ADMIN],
            'RGMO head' => [User::ROLE_RGMO_HEAD],
            'project manager' => [User::ROLE_PROJECT_MANAGER],
            'staff' => [User::ROLE_STAFF],
        ];
    }

    /**
     * Verify that users can not authenticate with invalid password.
     */
    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    /**
     * Verify that users can logout.
     */
    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}

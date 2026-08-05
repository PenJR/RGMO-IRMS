<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that password can be updated.
     */
    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'Secure123!',
                'password_confirmation' => 'Secure123!',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('Secure123!', $user->refresh()->password));
    }

    /**
     * Verify that correct password must be provided to update password.
     */
    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'Secure123!',
                'password_confirmation' => 'Secure123!',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }

    public function test_non_admin_cannot_update_their_own_password(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($user)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'Secure123!',
                'password_confirmation' => 'Secure123!',
            ])
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }
}

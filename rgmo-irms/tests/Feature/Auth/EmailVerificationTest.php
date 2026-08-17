<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_without_a_verified_email_can_access_the_application(): void
    {
        $user = User::factory()->unverified()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertDontSee('verification email', false)
            ->assertDontSee('email address is unverified', false);
    }

    public function test_email_verification_screen_is_removed(): void
    {
        $user = User::factory()->unverified()->create([
            'status' => User::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertRedirect(route('dashboard'));
    }
}

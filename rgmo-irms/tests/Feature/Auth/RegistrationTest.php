<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that registration screen can be rendered.
     */
    public function test_public_registration_screen_is_disabled(): void
    {
        $response = $this->get('/register');

        $response->assertNotFound();
    }

    /**
     * Verify that new users can register.
     */
    public function test_public_registration_submission_is_disabled(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'Secure123!',
            'password_confirmation' => 'Secure123!',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }
}

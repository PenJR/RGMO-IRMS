<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BootstrapAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BootstrapAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_database_gets_an_active_admin_who_can_log_in(): void
    {
        config(['bootstrap_admin' => [
            'name' => 'Recovery Admin',
            'email' => 'recovery@example.com',
            'password' => 'Recovery-Test-732!',
        ]]);

        $this->seed(BootstrapAdminSeeder::class);
        $admin = User::sole();
        $this->assertSame('admin', $admin->role);
        $this->assertSame('active', $admin->status);
        $this->assertTrue(Hash::check('Recovery-Test-732!', $admin->password));

        $this->post('/login', [
            'email' => 'recovery@example.com',
            'password' => 'Recovery-Test-732!',
        ])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_existing_users_are_never_overwritten_or_given_an_extra_admin(): void
    {
        $user = User::factory()->create();
        $original = $user->fresh()->getAttributes();
        config(['bootstrap_admin' => ['email' => null, 'password' => null, 'name' => null]]);

        $this->seed(BootstrapAdminSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($original, $user->fresh()->getAttributes());
    }

    public function test_invalid_credentials_do_not_create_an_account(): void
    {
        config(['bootstrap_admin' => ['email' => 'invalid', 'password' => 'password', 'name' => 'Admin']]);

        try {
            $this->seed(BootstrapAdminSeeder::class);
            $this->fail('Expected credential validation to fail.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('users', 0);
        }
    }
}

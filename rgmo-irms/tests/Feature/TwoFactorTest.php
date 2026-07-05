<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Verify that user can enable two factor authentication.
     */
    public function test_user_can_enable_two_factor_authentication(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->getJson('/2fa/enable');

        $response->assertOk();
        $response->assertJsonStructure(['secret', 'otpauth_url']);
        $response->assertJsonFragment(['otpauth_url' => $response->json('otpauth_url')]);

        $this->assertNotEmpty($response->json('secret'));
        $this->assertStringStartsWith('otpauth://', $response->json('otpauth_url'));
    }

    /**
     * Verify that user can confirm two factor authentication.
     */
    public function test_user_can_confirm_two_factor_authentication(): void
    {
        $user = User::factory()->create();
        $secret = app(TwoFactorService::class)->generateSecret();
        $user->update(['two_factor_secret' => $secret, 'two_factor_enabled' => false]);

        $totp = $this->currentTotp($secret);

        $session = ['_token' => 'testing'];
        $response = $this
            ->actingAs($user)
            ->withSession($session)
            ->post('/2fa/confirm', ['_token' => $session['_token'], 'code' => $totp]);

        $response->assertOk();
        $user->refresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertSame($secret, $user->two_factor_secret);
    }

    /**
     * Verify that login requires two factor verification.
     */
    public function test_login_requires_two_factor_verification(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $session = ['_token' => 'testing'];
        $response = $this
            ->withSession($session)
            ->post('/login', [
            '_token' => $session['_token'],
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/2fa/verify');
        $this->assertGuest();

        $verifyResponse = $this
            ->withSession($session)
            ->post('/2fa/verify', ['_token' => $session['_token'], 'code' => $this->currentTotp($secret)]);
        $verifyResponse->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    /**
     * Verify that api login requires two factor verification and can verify.
     */
    public function test_api_login_requires_two_factor_verification_and_can_verify(): void
    {
        $secret = app(TwoFactorService::class)->generateSecret();
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(202);
        $response->assertJson(['2fa_required' => true]);

        $verify = $this->postJson('/api/auth/verify', ['code' => $this->currentTotp($secret)]);
        $verify->assertOk();
        $verify->assertJson(['message' => 'Authenticated']);

        $this->assertAuthenticatedAs($user);
    }

    /**
     * Handle current totp.
     */
    private function currentTotp(string $secret): string
    {
        $service = app(TwoFactorService::class);
        $timeSlice = floor(time() / 30);
        $value = $this->invokePrivateMethod($service, 'getCode', [$secret, $timeSlice]);

        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Handle invoke private method.
     */
    private function invokePrivateMethod($object, string $method, array $parameters = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($method);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}

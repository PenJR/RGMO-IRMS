<?php

namespace Tests\Feature;

use App\Services\TurnstileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TurnstileServiceTest extends TestCase
{
    public function test_it_requires_a_valid_server_verified_token_when_configured(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);
        Http::fake(['https://challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        $request = Request::create('/login', 'POST', ['cf-turnstile-response' => 'valid-token']);
        $request->server->set('REMOTE_ADDR', '127.0.0.1');

        $this->assertTrue(app(TurnstileService::class)->verify($request));
        Http::assertSent(fn ($request) => $request->data()['secret'] === 'secret-key'
            && $request->data()['response'] === 'valid-token');
    }

    public function test_it_rejects_missing_tokens_when_configured(): void
    {
        config([
            'services.turnstile.site_key' => 'site-key',
            'services.turnstile.secret_key' => 'secret-key',
        ]);

        $this->assertFalse(app(TurnstileService::class)->verify(Request::create('/login', 'POST')));
    }
}

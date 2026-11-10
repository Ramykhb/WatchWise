<?php

namespace Tests\Feature\Auth;

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Behind a trusted proxy the client address comes from X-Forwarded-For, so IP-keyed limits count per visitor
 * instead of putting every visitor in the proxy's bucket; from an untrusted peer the header is ignored. Only
 * X-Forwarded-For and X-Forwarded-Proto are believed: the host and port of generated links cannot be chosen.
 */
class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = '10.0.0.1';

    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/api/_test/request', fn (Request $request) => [
            'secure' => $request->secure(),
            'host' => $request->getHost(),
            'port' => $request->getPort(),
            'link' => URL::temporarySignedRoute('test.signed', now()->addHour()),
        ]);
        Route::get('/api/_test/signed', fn () => ['ok' => true])->middleware('signed')->name('test.signed');
        Route::getRoutes()->refreshNameLookups();
    }

    /** @param  array<string, string>  $headers */
    private function fromProxy(array $headers): static
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])->withHeaders($headers);
    }

    /** Production: generated URLs are pinned to APP_URL over https. */
    private function pinUrlsLikeProduction(string $appUrl): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['app.url' => $appUrl]);
        $this->app->getProvider(AppServiceProvider::class)->configureUrlGeneration();
    }

    /** No email in the body, so only the per-IP bucket of `auth-login` (5 per minute) counts; validation answers 422. */
    private function loginFrom(string $client): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => self::PROXY])
            ->withHeader('X-Forwarded-For', $client)
            ->postJson('/api/auth/login');
    }

    public function test_a_trusted_proxy_gives_each_client_its_own_bucket(): void
    {
        config(['trustedproxy.proxies' => '*']);

        for ($i = 0; $i < 5; $i++) {
            $this->loginFrom('203.0.113.5')->assertUnprocessable();
        }

        $this->loginFrom('203.0.113.5')->assertTooManyRequests();
        $this->loginFrom('203.0.113.6')->assertUnprocessable();
    }

    public function test_a_listed_proxy_address_is_trusted_too(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.0/8, 192.168.0.1']);

        for ($i = 0; $i < 5; $i++) {
            $this->loginFrom('203.0.113.5')->assertUnprocessable();
        }

        $this->loginFrom('203.0.113.6')->assertUnprocessable();
    }

    public function test_forwarded_for_is_ignored_from_an_untrusted_peer(): void
    {
        config(['trustedproxy.proxies' => null]);

        for ($i = 0; $i < 5; $i++) {
            $this->loginFrom("203.0.113.{$i}")->assertUnprocessable();
        }

        $this->loginFrom('203.0.113.99')->assertTooManyRequests();
    }

    public function test_a_peer_outside_the_listed_proxies_is_not_trusted(): void
    {
        config(['trustedproxy.proxies' => '192.168.0.1']);

        for ($i = 0; $i < 5; $i++) {
            $this->loginFrom("203.0.113.{$i}")->assertUnprocessable();
        }

        $this->loginFrom('203.0.113.99')->assertTooManyRequests();
    }

    public function test_a_forwarded_host_from_a_trusted_proxy_cannot_change_the_host_of_a_signed_link(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $response = $this->fromProxy(['X-Forwarded-Host' => 'evil.example'])->getJson('http://api.test/api/_test/request')->assertOk();

        $this->assertSame('api.test', $response->json('host'));
        $this->assertStringStartsWith('http://api.test/api/_test/signed?', $response->json('link'));
    }

    public function test_in_production_signed_links_use_app_url_whatever_host_the_request_names(): void
    {
        config(['trustedproxy.proxies' => '*']);
        $this->pinUrlsLikeProduction('https://api.example.com');

        $response = $this->fromProxy(['X-Forwarded-Host' => 'evil.example'])
            ->getJson('http://evil.example/api/_test/request')->assertOk();

        $this->assertStringStartsWith('https://api.example.com/api/_test/signed?', $response->json('link'));
    }

    public function test_a_production_link_validates_when_it_arrives_through_the_proxy_as_https(): void
    {
        config(['trustedproxy.proxies' => '*']);
        $this->pinUrlsLikeProduction('https://api.example.com');
        $link = URL::temporarySignedRoute('test.signed', now()->addHour());
        // What the container sees: plain http from the proxy, with the original scheme in X-Forwarded-Proto.
        $asReceived = 'http://'.substr($link, strlen('https://'));

        $this->fromProxy(['X-Forwarded-Proto' => 'https'])->getJson($asReceived)->assertOk();

        config(['trustedproxy.proxies' => null]);
        $this->fromProxy(['X-Forwarded-Proto' => 'https'])->getJson($asReceived)->assertForbidden();
    }

    public function test_forwarded_proto_is_honoured_only_from_a_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => '*']);
        $trusted = $this->fromProxy(['X-Forwarded-Proto' => 'https'])->getJson('http://api.test/api/_test/request')->assertOk();
        $this->assertTrue($trusted->json('secure'));
        $this->assertStringStartsWith('https://api.test/', $trusted->json('link'));

        config(['trustedproxy.proxies' => null]);
        $untrusted = $this->fromProxy(['X-Forwarded-Proto' => 'https'])->getJson('http://api.test/api/_test/request')->assertOk();
        $this->assertFalse($untrusted->json('secure'));
    }

    public function test_forwarded_port_is_ignored(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $response = $this->fromProxy(['X-Forwarded-Port' => '8443'])->getJson('http://api.test/api/_test/request')->assertOk();

        $this->assertSame(80, $response->json('port'));
        $this->assertStringStartsWith('http://api.test/api/_test/signed?', $response->json('link'));
    }

    public function test_production_trusts_the_calling_proxy_by_default_and_other_environments_trust_none(): void
    {
        $this->assertSame('*', $this->configWith(['APP_ENV' => 'production', 'TRUSTED_PROXIES' => null]));
        $this->assertNull($this->configWith(['APP_ENV' => 'local', 'TRUSTED_PROXIES' => null]));
        $this->assertSame('10.0.0.0/8,10.1.0.1', $this->configWith(['APP_ENV' => 'local', 'TRUSTED_PROXIES' => ' 10.0.0.0/8,10.1.0.1 ']));
        $this->assertNull($this->configWith(['APP_ENV' => 'production', 'TRUSTED_PROXIES' => '']));
    }

    /**
     * Evaluates config/trustedproxy.php with the given environment variables (null = unset), then restores them.
     *
     * @param  array<string, string|null>  $variables
     */
    private function configWith(array $variables): mixed
    {
        $saved = [];

        foreach ($variables as $name => $value) {
            $saved[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null, getenv($name)];
            $this->setEnvironmentVariable($name, $value);
        }

        try {
            return (require config_path('trustedproxy.php'))['proxies'];
        } finally {
            foreach ($saved as $name => [$server, $env, $process]) {
                $this->setEnvironmentVariable($name, $server ?? $env ?? ($process === false ? null : $process));
            }
        }
    }

    private function setEnvironmentVariable(string $name, ?string $value): void
    {
        if ($value === null) {
            unset($_SERVER[$name], $_ENV[$name]);
            putenv($name);

            return;
        }

        $_SERVER[$name] = $_ENV[$name] = $value;
        putenv("{$name}={$value}");
    }
}

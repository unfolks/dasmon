<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/_testing/proxy', fn (Request $request): array => [
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
            'asset' => asset('build/assets/app.js'),
        ]);
    }

    public function test_requests_from_the_configured_proxy_use_https_and_the_client_ip(): void
    {
        config(['trustedproxy.proxies' => '172.18.0.3']);

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.3'])
            ->getJson('http://monitor.example.com/_testing/proxy', [
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-For' => '203.0.113.10',
            ])
            ->assertOk()
            ->assertExactJson([
                'secure' => true,
                'ip' => '203.0.113.10',
                'asset' => 'https://monitor.example.com/build/assets/app.js',
            ]);
    }

    public function test_requests_from_other_addresses_ignore_forwarded_headers(): void
    {
        config(['trustedproxy.proxies' => '172.18.0.3']);

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.4'])
            ->getJson('http://monitor.example.com/_testing/proxy', [
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-For' => '203.0.113.10',
            ])
            ->assertOk()
            ->assertExactJson([
                'secure' => false,
                'ip' => '172.18.0.4',
                'asset' => 'http://monitor.example.com/build/assets/app.js',
            ]);
    }

    public function test_forwarded_headers_are_ignored_when_no_proxy_is_configured(): void
    {
        config(['trustedproxy.proxies' => null]);

        $this->withServerVariables(['REMOTE_ADDR' => '172.18.0.3'])
            ->getJson('http://monitor.example.com/_testing/proxy', [
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-For' => '203.0.113.10',
            ])
            ->assertOk()
            ->assertExactJson([
                'secure' => false,
                'ip' => '172.18.0.3',
                'asset' => 'http://monitor.example.com/build/assets/app.js',
            ]);
    }
}

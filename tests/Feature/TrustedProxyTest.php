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

        Route::get('/_scheme', fn (Request $request): string => $request->getScheme());
    }

    public function test_forwarded_https_is_honoured_from_the_lan_proxy(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.10'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/_scheme');

        $response->assertOk();
        $response->assertSeeText('https');
    }

    public function test_forwarded_https_is_ignored_from_an_untrusted_address(): void
    {
        $response = $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.5'])
            ->withHeader('X-Forwarded-Proto', 'https')
            ->get('/_scheme');

        $response->assertOk();
        $response->assertSeeText('http');
        $response->assertDontSeeText('https');
    }
}

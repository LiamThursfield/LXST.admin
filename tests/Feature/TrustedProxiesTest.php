<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::get('/proxy-check', fn (Request $request) => [
        'ip' => $request->ip(),
        'scheme' => $request->getScheme(),
        'host' => $request->getHost(),
        'port' => $request->getPort(),
    ]);
});

test('the visitor ip address and scheme are taken from a cloudflare proxy', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Proto' => 'https',
        ])
        ->get('http://localhost/proxy-check');

    $response->assertJson(['ip' => '203.0.113.7', 'scheme' => 'https', 'port' => 443]);
});

test('the visitor ip address is taken from a cloudflare proxy on ipv6', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '2606:4700::1'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->get('http://localhost/proxy-check');

    $response->assertJson(['ip' => '203.0.113.7']);
});

test('forwarded headers are ignored from anywhere other than a trusted proxy', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])
        ->withHeaders([
            'X-Forwarded-For' => '203.0.113.7',
            'X-Forwarded-Proto' => 'https',
        ])
        ->get('http://localhost/proxy-check');

    $response->assertJson(['ip' => '198.51.100.20', 'scheme' => 'http']);
});

test('a trusted proxy cannot change the host or port', function () {
    $response = $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10'])
        ->withHeaders([
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Port' => '8443',
        ])
        ->get('http://localhost/proxy-check');

    $response->assertJson(['host' => 'localhost', 'port' => 80]);
});

test('other proxies can be trusted instead of cloudflare', function () {
    config(['trustedproxy.proxies' => '10.0.0.5']);

    $fromOwnProxy = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->get('http://localhost/proxy-check');

    $fromCloudflare = $this->withServerVariables(['REMOTE_ADDR' => '173.245.48.10'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.7'])
        ->get('http://localhost/proxy-check');

    $fromOwnProxy->assertJson(['ip' => '203.0.113.7']);
    $fromCloudflare->assertJson(['ip' => '173.245.48.10']);
});

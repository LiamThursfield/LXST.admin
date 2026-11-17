<?php

use App\Models\ApiToken;
use App\Models\McpAuditLog;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use Database\Seeders\McpSeeder;
use Illuminate\Support\Facades\Auth;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions([SettingsPermission::ManageApiTokens, CmsPermission::ViewContent, CmsPermission::ManageContent]);
    app(McpSeeder::class)->run($this->user);
});

test('the MCP seeder gives the user a fixed token that can draft content but not publish, with some activity', function () {
    $token = McpSeeder::token();

    expect($token->tokenable->is($this->user))->toBeTrue()
        ->and($token->abilities)->not->toContain('mcp:cms:publish')
        ->and($token->expires_at->isFuture())->toBeTrue()
        ->and(McpAuditLog::query()->where('api_token_id', $token->id)->count())->toBeGreaterThan(0);

    Auth::forgetGuards();

    $tools = $this->withToken(McpSeeder::TOKEN)
        ->postJson(route('api.mcp'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertOk()
        ->json('result.tools.*.name');

    expect($tools)->toContain('create_page')->not->toContain('publish_page');
});

test('running the MCP seeder again creates nothing new', function () {
    $logs = McpAuditLog::count();

    app(McpSeeder::class)->run($this->user);

    expect(ApiToken::count())->toBe(1)
        ->and(McpAuditLog::count())->toBe($logs);
});

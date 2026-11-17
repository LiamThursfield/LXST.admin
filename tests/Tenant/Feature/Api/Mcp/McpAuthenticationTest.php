<?php

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Api\McpOutcome;
use App\Enums\TenantModule;
use App\Models\ApiToken;
use App\Models\McpAuditLog;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Authorisation\Enums\SettingsPermission;
use Illuminate\Support\Facades\Auth;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = mcpUser($this);
});

test('without a token, the server refuses with a 401 and says how to authenticate', function () {
    mcpRequest($this, null, 'tools/list')
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

test('an invalid, expired or revoked token is refused', function () {
    mcpRequest($this, 'lxst_NotARealToken', 'tools/list')->assertUnauthorized();

    $expired = mcpToken($this->user);
    ApiToken::query()->latest('id')->first()->update(['expires_at' => now()->subMinute()]);
    mcpRequest($this, $expired, 'tools/list')->assertUnauthorized();

    $revoked = mcpToken($this->user);
    ApiToken::query()->latest('id')->first()->delete();
    mcpRequest($this, $revoked, 'tools/list')->assertUnauthorized();
});

test('a signed-in admin session without a token is refused', function () {
    $this->actingAs($this->user);

    mcpRequest($this, null, 'tools/list')->assertUnauthorized();
});

test('a token without any MCP ability is refused, even one made in code with every ability', function () {
    mcpRequest($this, mcpToken($this->user, [ApiTokenAbility::DjNowPlaying]), 'tools/list')->assertForbidden();

    mcpRequest($this, $this->user->createToken('Everything')->plainTextToken, 'tools/list')->assertForbidden();
});

test('a token stops working when its user can no longer manage API tokens', function () {
    $token = mcpToken($this->user);
    $this->user->revokePermissionTo(SettingsPermission::ManageApiTokens);

    mcpRequest($this, $token, 'tools/list')->assertUnauthorized();
});

test('the server is only on the site\'s own domains, not the central domain', function () {
    $centralDomain = config('tenancy.identification.central_domains')[0];

    $this->withToken(mcpToken($this->user))
        ->postJson("http://{$centralDomain}/api/mcp", ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertNotFound();
});

test('a token from another site is not known here', function () {
    // Each site's tokens are in its own database, so another site's token is just an unknown one here
    $otherSitesToken = '1|lxst_'.str_repeat('a', 40);

    mcpRequest($this, $otherSitesToken, 'tools/list')->assertUnauthorized();
});

test('a token only sees the tools for its abilities', function () {
    $readOnly = mcpToken($this->user, [ApiTokenAbility::McpCmsRead]);

    expect(mcpToolNames($this, $readOnly))->toBe([
        'get_menu', 'get_page', 'get_site_settings', 'get_template', 'list_field_types',
        'list_layouts', 'list_menus', 'list_pages', 'list_templates',
    ]);

    $forms = mcpToken($this->user, [ApiTokenAbility::McpCrmForms]);

    expect(mcpToolNames($this, $forms))->toBe(['create_form', 'get_form', 'list_forms', 'update_form']);

    expect(mcpToolNames($this, mcpToken($this->user)))->toHaveCount(21);
});

test('a module\'s tools are only there when the site has the module', function () {
    tenant()->update(['modules' => [TenantModule::Crm]]);

    expect(mcpToolNames($this, mcpToken($this->user)))->toBe(['create_form', 'get_form', 'list_forms', 'update_form']);
});

test('tools are only there when the token\'s user has the permissions the admin needs', function () {
    $contentEditor = $this->createUserWithPermissions([SettingsPermission::ManageApiTokens, CmsPermission::ViewContent, CmsPermission::ManageContent]);

    expect(mcpToolNames($this, mcpToken($contentEditor)))->toBe([
        'create_page', 'get_page', 'get_site_settings', 'list_field_types', 'list_layouts',
        'list_pages', 'publish_page', 'unpublish_page', 'update_page',
    ]);
});

test('a tool the token can\'t use can\'t be called either', function () {
    $readOnly = mcpToken($this->user, [ApiTokenAbility::McpCmsRead]);

    $result = mcpCall($this, $readOnly, 'create_page', ['name' => 'Sneaky']);

    expect($result['error']['message'])->toContain('create_page')
        ->and(McpAuditLog::count())->toBe(0);
});

test('every tool call is audit logged with its token, user and outcome', function () {
    $token = mcpToken($this->user, [ApiTokenAbility::McpCmsRead]);

    mcpCall($this, $token, 'list_pages');
    mcpCall($this, $token, 'get_page', ['id' => 999]);

    [$listed, $missing] = McpAuditLog::query()->orderBy('id')->get();
    $apiToken = ApiToken::query()->latest('id')->first();

    expect($listed)
        ->tool->toBe('list_pages')
        ->ability->toBe('mcp:cms:read')
        ->outcome->toBe(McpOutcome::Success)
        ->api_token_id->toBe($apiToken->id)
        ->api_token_name->toBe('Claude Code')
        ->user_id->toBe($this->user->id)
        ->and($missing)
        ->tool->toBe('get_page')
        ->outcome->toBe(McpOutcome::Denied)
        ->arguments->toBe(['id' => 999]);
});

test('a browser request from another site\'s origin is refused, even with a valid token', function () {
    Auth::forgetGuards();

    $this->withToken(mcpToken($this->user))
        ->withHeader('Origin', 'https://evil.example')
        ->postJson(route('api.mcp'), ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])
        ->assertForbidden();
});

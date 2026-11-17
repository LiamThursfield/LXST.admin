<?php

use App\Enums\Api\McpOutcome;
use App\Models\ApiToken;
use App\Models\McpAuditLog;
use App\Services\Authorisation\Enums\SettingsPermission;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = $this->createUserWithPermissions(SettingsPermission::cases(), ['timezone' => 'Europe/London']);
    $this->actingAs($this->user);
});

test('the MCP activity lists every tool call, newest first, with revoked tokens named', function () {
    $token = ApiToken::factory()->for($this->user, 'tokenable')->create(['name' => 'Claude Code']);
    McpAuditLog::factory()->for($token)->create(['tool' => 'list_pages']);
    McpAuditLog::factory()->for($token)->createdPage(4, 'About')->create();
    McpAuditLog::factory()->denied()->create(['api_token_id' => null, 'api_token_name' => 'Old laptop']);

    $this->get(route('admin.site-settings.api-tokens.activity'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/site-settings/McpActivity')
            ->has('entries.data', 3)
            ->where('entries.data.0.token', 'Old laptop (revoked)')
            ->where('entries.data.0.outcome.value', 'denied')
            ->where('entries.data.1.tool', 'create_page')
            ->where('entries.data.1.target', 'page #4')
            ->where('entries.data.1.changes.name.after', 'About')
            ->where('entries.data.2.token', 'Claude Code')
            ->where('tokens', [['value' => (string) $token->id, 'label' => 'Claude Code']])
        );
});

test('the MCP activity can be filtered by token and outcome', function () {
    $token = ApiToken::factory()->create();
    McpAuditLog::factory()->for($token)->create();
    McpAuditLog::factory()->for($token)->invalid()->create();
    McpAuditLog::factory()->invalid()->create();

    $this->get(route('admin.site-settings.api-tokens.activity', ['filter' => ['token' => $token->id, 'outcome' => McpOutcome::Invalid->value]]))
        ->assertInertia(fn (Assert $page) => $page->has('entries.data', 1));
});

test('only users who can manage API tokens see the MCP activity', function () {
    $this->actingAs($this->createUserWithPermissions([SettingsPermission::ViewSettings]))
        ->get(route('admin.site-settings.api-tokens.activity'))
        ->assertForbidden();
});

test('the tokens page suggests an expiry for MCP tokens on the user\'s clock', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-17 23:30', 'UTC'));
    $this->user->update(['timezone' => 'Asia/Tokyo']);

    $this->get(route('admin.site-settings.api-tokens.index'))
        ->assertInertia(fn (Assert $page) => $page->where('suggestedMcpExpiry', '2027-02-16'));
});

test('old MCP activity is pruned after the retention period', function () {
    config(['api.mcp.audit_retention_days' => 30]);
    $old = McpAuditLog::factory()->create(['created_at' => now()->subDays(31)]);
    $recent = McpAuditLog::factory()->create(['created_at' => now()->subDays(29)]);

    $this->artisan('api:prune-mcp-audit-logs', ['--tenant' => tenant()->getTenantKey()])->assertSuccessful();

    expect(McpAuditLog::query()->pluck('id')->all())->toBe([$recent->id])
        ->and($old->fresh())->toBeNull();
});

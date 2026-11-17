<?php

namespace Database\Seeders;

use App\Enums\Api\ApiTokenAbility;
use App\Models\ApiToken;
use App\Models\Cms\Page;
use App\Models\McpAuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A token for trying the MCP server (docs/api/mcp.md) straight after a reset, e.g. by connecting
 * Claude Code to the local site, and some MCP activity to show in Site settings › API tokens. The
 * token can read and draft content, and change templates and menus, but not publish. Its value is
 * fixed, like the role users' passwords, so a local MCP client config keeps working. It's a local
 * test token only.
 *
 * Run within a tenant with the CMS module, after the role users and the CMS seeder.
 * Safe to re-run: the token and its activity are only made when the user doesn't have the token.
 */
class McpSeeder extends Seeder
{
    public const string NAME = 'Claude Code (local)';

    /** The plain token, as an MCP client sends it */
    public const string TOKEN = 'lxst_LocalDemoMcpToken0000000000000000000000';

    /** @var list<ApiTokenAbility> */
    public const array ABILITIES = [
        ApiTokenAbility::McpCmsRead,
        ApiTokenAbility::McpCmsContent,
        ApiTokenAbility::McpCmsStructure,
        ApiTokenAbility::McpCrmForms,
    ];

    public function run(?User $user = null): void
    {
        if ($user === null || $user->tokens()->where('name', self::NAME)->exists()) {
            return;
        }

        /** @var ApiToken $token */
        $token = $user->tokens()->create([
            'name' => self::NAME,
            'token' => hash('sha256', self::TOKEN),
            'abilities' => array_map(fn (ApiTokenAbility $ability) => $ability->value, self::ABILITIES),
            'expires_at' => now()->addDays((int) config('api.mcp.suggested_expiry_days')),
        ]);

        $this->seedActivity($token, $user);

        $this->command?->info(self::NAME.' MCP token ('.$user->email.'): '.self::TOKEN);
    }

    /**
     * A short session: reading the pages, drafting one, a page that failed validation, and an
     * attempt to publish that the token wasn't allowed
     */
    protected function seedActivity(ApiToken $token, User $user): void
    {
        $page = Page::query()->orderBy('id')->first();
        $base = ['api_token_id' => $token->id, 'api_token_name' => $token->name, 'user_id' => $user->id, 'ip_address' => '127.0.0.1'];

        $entries = [
            McpAuditLog::factory()->state([...$base, 'created_at' => now()->subMinutes(12)]),
            McpAuditLog::factory()->invalid()->state([...$base, 'created_at' => now()->subMinutes(9)]),
            $page === null ? null : McpAuditLog::factory()->createdPage($page->id, $page->name)->state([...$base, 'created_at' => now()->subMinutes(8)]),
            McpAuditLog::factory()->denied()->state([...$base, 'target_type' => $page ? 'page' : null, 'target_id' => $page?->id, 'created_at' => now()->subMinutes(5)]),
        ];

        foreach (array_filter($entries) as $entry) {
            $entry->create();
        }
    }

    /**
     * The seeded token, if it's there
     */
    public static function token(): ?ApiToken
    {
        return ApiToken::query()->where('token', hash('sha256', self::TOKEN))->first();
    }
}

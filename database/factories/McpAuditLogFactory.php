<?php

namespace Database\Factories;

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Api\McpOutcome;
use App\Models\ApiToken;
use App\Models\McpAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpAuditLog>
 */
class McpAuditLogFactory extends Factory
{
    protected $model = McpAuditLog::class;

    /**
     * Define the model's default state (a successful read of the page list).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'api_token_id' => ApiToken::factory(),
            'api_token_name' => fn (array $attributes) => ApiToken::query()->find($attributes['api_token_id'])?->name,
            'user_id' => fn (array $attributes) => ApiToken::query()->find($attributes['api_token_id'])?->tokenable_id,
            'tool' => 'list_pages',
            'ability' => ApiTokenAbility::McpCmsRead->value,
            'outcome' => McpOutcome::Success,
            'target_type' => null,
            'target_id' => null,
            'arguments' => [],
            'changes' => null,
            'message' => null,
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'claude-code/2.0',
        ];
    }

    /**
     * A draft page created through MCP
     */
    public function createdPage(int $pageId, string $name): static
    {
        return $this->state(fn () => [
            'tool' => 'create_page',
            'ability' => ApiTokenAbility::McpCmsContent->value,
            'target_type' => 'page',
            'target_id' => $pageId,
            'arguments' => ['name' => $name],
            'changes' => ['name' => ['before' => null, 'after' => $name]],
        ]);
    }

    /**
     * A call refused because the token didn't have the ability it needed
     */
    public function denied(string $tool = 'publish_page'): static
    {
        return $this->state(fn () => [
            'tool' => $tool,
            'ability' => ApiTokenAbility::McpCmsPublish->value,
            'outcome' => McpOutcome::Denied,
            'message' => 'This API token doesn\'t have the mcp:cms:publish ability.',
        ]);
    }

    /**
     * A write whose arguments failed validation
     */
    public function invalid(): static
    {
        return $this->state(fn () => [
            'tool' => 'create_page',
            'ability' => ApiTokenAbility::McpCmsContent->value,
            'outcome' => McpOutcome::Invalid,
            'message' => 'The name field is required.',
        ]);
    }
}

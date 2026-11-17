<?php

namespace App\Models;

use App\Enums\Api\McpOutcome;
use Carbon\CarbonImmutable;
use Database\Factories\McpAuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tool call made to the MCP server with a tenant API token: which token and user, the tool,
 * what it acted on, how it ended, and what it changed. Pruned after `api.mcp.audit_retention_days`.
 *
 * @property int $id
 * @property int|null $api_token_id
 * @property string|null $api_token_name
 * @property int|null $user_id
 * @property string $tool
 * @property string $ability
 * @property McpOutcome $outcome
 * @property string|null $target_type
 * @property int|null $target_id
 * @property array<string, mixed>|null $arguments
 * @property array<string, array{before: mixed, after: mixed}>|null $changes
 * @property string|null $message
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 * @property-read ApiToken|null $apiToken
 * @property-read User|null $user
 */
class McpAuditLog extends Model
{
    /** @use HasFactory<McpAuditLogFactory> */
    use HasFactory;

    use MassPrunable;

    public const UPDATED_AT = null;

    protected $fillable = [
        'api_token_id',
        'api_token_name',
        'user_id',
        'tool',
        'ability',
        'outcome',
        'target_type',
        'target_id',
        'arguments',
        'changes',
        'message',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => McpOutcome::class,
            'arguments' => 'array',
            'changes' => 'array',
            'target_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ApiToken, $this>
     */
    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Entries older than the retention period
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<', now()->subDays((int) config('api.mcp.audit_retention_days')));
    }
}

<?php

namespace App\Mcp\Support;

use App\Models\ApiToken;
use App\Models\McpAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Saves each MCP tool call to the tenant's audit log
 */
class AuditTrail
{
    public function __construct(protected Request $request) {}

    public function record(ToolCall $call, ?User $user, ?ApiToken $token): McpAuditLog
    {
        /** @var array<string, mixed> $arguments */
        $arguments = Summary::of($call->arguments);

        return McpAuditLog::create([
            'api_token_id' => $token?->id,
            'api_token_name' => $token?->name,
            'user_id' => $user?->id,
            'tool' => $call->tool,
            'ability' => $call->ability->value,
            'outcome' => $call->outcome,
            'target_type' => $call->targetType,
            'target_id' => $call->targetId,
            'arguments' => $arguments,
            'changes' => $call->changes,
            'message' => $call->message === null ? null : Str::limit($call->message, 1000),
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 250, ''),
        ]);
    }
}

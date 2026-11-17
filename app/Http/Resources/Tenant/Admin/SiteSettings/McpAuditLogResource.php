<?php

namespace App\Http\Resources\Tenant\Admin\SiteSettings;

use App\Models\McpAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class McpAuditLogResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var McpAuditLog $log */
        $log = $this->resource;

        return [
            'id' => $log->id,
            'token' => match (true) {
                $log->apiToken !== null => $log->apiToken->name,
                $log->api_token_name !== null => __('mcp.activity.revoked_token', ['name' => $log->api_token_name]),
                default => __('mcp.activity.unknown_token'),
            },
            'user' => $log->user?->name,
            'tool' => $log->tool,
            'ability' => $log->ability,
            'target' => $log->target_type === null ? null : "{$log->target_type} #{$log->target_id}",
            'outcome' => $log->outcome->toSelectItem(),
            'message' => $log->message,
            'arguments' => (object) ($log->arguments ?? []),
            'changes' => $log->changes,
            'ip_address' => $log->ip_address,
            'created_at' => $log->created_at->toIso8601String(),
        ];
    }
}

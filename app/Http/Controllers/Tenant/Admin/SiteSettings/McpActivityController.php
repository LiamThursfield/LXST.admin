<?php

namespace App\Http\Controllers\Tenant\Admin\SiteSettings;

use App\Enums\Api\McpOutcome;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\SiteSettings\McpActivityIndexQuery;
use App\Http\Requests\Tenant\Admin\SiteSettings\McpActivityIndexRequest;
use App\Http\Resources\Tenant\Admin\SiteSettings\McpAuditLogResource;
use App\Models\ApiToken;
use App\Models\McpAuditLog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The MCP audit log: every tool call made to the site's MCP server, for whoever manages API tokens
 */
class McpActivityController extends Controller
{
    public function index(McpActivityIndexRequest $request, McpActivityIndexQuery $query): Response
    {
        return Inertia::render('admin/site-settings/McpActivity', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'created_at', 'header' => __('mcp.activity.columns.time')],
                ['accessorKey' => 'token', 'header' => __('mcp.activity.columns.token')],
                ['accessorKey' => 'tool', 'header' => __('mcp.activity.columns.tool')],
                ['accessorKey' => 'outcome', 'header' => __('mcp.activity.columns.outcome')],
                ['accessorKey' => 'details', 'header' => ''],
            ],
            'entries' => McpAuditLogResource::collection($query->handle($request)),
            // Tokens that have used MCP, including revoked ones still in the log
            'tokens' => ApiToken::query()
                ->whereIn('id', McpAuditLog::query()->select('api_token_id')->whereNotNull('api_token_id'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (ApiToken $token) => ['value' => (string) $token->id, 'label' => $token->name])
                ->all(),
            'outcomes' => McpOutcome::asSelectItems(),
            'retentionDays' => (int) config('api.mcp.audit_retention_days'),
        ]);
    }
}

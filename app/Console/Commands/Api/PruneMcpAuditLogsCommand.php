<?php

namespace App\Console\Commands\Api;

use App\Models\McpAuditLog;
use App\Models\Tenant;
use Illuminate\Console\Command;

class PruneMcpAuditLogsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:prune-mcp-audit-logs
        {--tenant= : The ID of a single tenant to prune for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete MCP audit log entries older than the retention period (api.mcp.audit_retention_days), for every tenant';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = is_string($tenantId) && $tenantId !== ''
            ? Tenant::query()->whereKey($tenantId)->get()
            : Tenant::all();

        foreach ($tenants as $tenant) {
            /** @var Tenant $tenant */
            $tenant->run(function () use ($tenant): void {
                $pruned = (new McpAuditLog)->pruneAll();

                $this->info("Pruned {$pruned} MCP audit log entries for tenant: {$tenant->getTenantKey()}");
            });
        }

        return Command::SUCCESS;
    }
}

<?php

namespace App\Console\Commands\Tracker;

use App\Models\Tenant;
use App\Services\Tracker\OccurrenceGenerator;
use Illuminate\Console\Command;

class GenerateOccurrencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:generate-occurrences
        {--tenant= : The ID of a single tenant to generate for}
        {--days-ahead=0 : How many days beyond today to generate}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate tracker task occurrences for active task definitions across tenants';

    /**
     * Execute the console command.
     */
    public function handle(OccurrenceGenerator $generator): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $tenantId = $this->option('tenant');
        $tenantId = is_string($tenantId) ? $tenantId : null;

        if ($tenantId) {
            $this->generateForTenant(Tenant::findOrFail($tenantId), $generator, $daysAhead);
        } else {
            Tenant::all()->each(function ($tenant) use ($generator, $daysAhead): void {
                /** @var Tenant $tenant - required for phpstan */
                $this->generateForTenant($tenant, $generator, $daysAhead);
            });
        }

        $this->info('Occurrence generation completed.');

        return Command::SUCCESS;
    }

    /**
     * Generate occurrences within a single tenant's context, up to each
     * owner's local today plus the days ahead.
     */
    protected function generateForTenant(Tenant $tenant, OccurrenceGenerator $generator, int $daysAhead): void
    {
        $tenant->run(function () use ($tenant, $generator, $daysAhead): void {
            $created = $generator->generateForTenant(daysAhead: $daysAhead);

            $this->info("Generated {$created} occurrences for tenant: {$tenant->getTenantKey()}");
        });
    }
}

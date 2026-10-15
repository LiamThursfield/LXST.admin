<?php

namespace App\Console\Commands\Tracker;

use App\Models\Tenant;
use App\Services\Tracker\OccurrenceGenerator;
use Carbon\CarbonImmutable;
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
        $upTo = CarbonImmutable::today()->addDays((int) $this->option('days-ahead'));
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $this->generateForTenant(Tenant::findOrFail($tenantId), $generator, $upTo);
        } else {
            Tenant::all()->each(function ($tenant) use ($generator, $upTo): void {
                /** @var Tenant $tenant - required for phpstan */
                $this->generateForTenant($tenant, $generator, $upTo);
            });
        }

        $this->info('Occurrence generation completed.');

        return Command::SUCCESS;
    }

    /**
     * Generate occurrences within a single tenant's context.
     */
    protected function generateForTenant(Tenant $tenant, OccurrenceGenerator $generator, CarbonImmutable $upTo): void
    {
        $tenant->run(function () use ($tenant, $generator, $upTo): void {
            $created = $generator->generateForTenant($upTo);

            $this->info("Generated {$created} occurrences for tenant: {$tenant->getTenantKey()}");
        });
    }
}

<?php

namespace App\Console\Commands\Tracker;

use App\Models\Tenant;
use App\Services\Tracker\OccurrenceEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class EvaluateOccurrencesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tracker:evaluate-occurrences
        {--tenant= : The ID of a single tenant to evaluate for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Finalise tracker occurrences whose period has ended across tenants';

    /**
     * Execute the console command.
     */
    public function handle(OccurrenceEvaluator $evaluator): int
    {
        $today = CarbonImmutable::today();
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $this->evaluateForTenant(Tenant::findOrFail($tenantId), $evaluator, $today);
        } else {
            Tenant::all()->each(function ($tenant) use ($evaluator, $today): void {
                /** @var Tenant $tenant - required for phpstan */
                $this->evaluateForTenant($tenant, $evaluator, $today);
            });
        }

        $this->info('Occurrence evaluation completed.');

        return Command::SUCCESS;
    }

    /**
     * Finalise due occurrences within a single tenant's context.
     */
    protected function evaluateForTenant(Tenant $tenant, OccurrenceEvaluator $evaluator, CarbonImmutable $today): void
    {
        $tenant->run(function () use ($tenant, $evaluator, $today): void {
            $evaluated = $evaluator->evaluateDueForTenant($today);

            $this->info("Evaluated {$evaluated} occurrences for tenant: {$tenant->getTenantKey()}");
        });
    }
}

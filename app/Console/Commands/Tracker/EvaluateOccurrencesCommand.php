<?php

namespace App\Console\Commands\Tracker;

use App\Models\Tenant;
use App\Services\Tracker\OccurrenceEvaluator;
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
        $tenantId = $this->option('tenant');

        if ($tenantId) {
            $this->evaluateForTenant(Tenant::findOrFail($tenantId), $evaluator);
        } else {
            Tenant::all()->each(function ($tenant) use ($evaluator): void {
                /** @var Tenant $tenant - required for phpstan */
                $this->evaluateForTenant($tenant, $evaluator);
            });
        }

        $this->info('Occurrence evaluation completed.');

        return Command::SUCCESS;
    }

    /**
     * Finalise due occurrences within a single tenant's context. Each
     * occurrence closes at its owner's local midnight.
     */
    protected function evaluateForTenant(Tenant $tenant, OccurrenceEvaluator $evaluator): void
    {
        $tenant->run(function () use ($tenant, $evaluator): void {
            $evaluated = $evaluator->evaluateDueForTenant();

            $this->info("Evaluated {$evaluated} occurrences for tenant: {$tenant->getTenantKey()}");
        });
    }
}

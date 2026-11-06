<?php

namespace App\Console\Commands\Bookings;

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Services\Bookings\ScheduledBookingMessages;
use Illuminate\Console\Command;

class SendScheduledMessagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bookings:send-scheduled-messages
        {--tenant= : The ID of a single tenant to send for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send the booking reminders, follow-ups and review requests that are due, for tenants with the Bookings module';

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
            if (! $tenant->hasModule(TenantModule::Bookings) || $tenant->isDisabled()) {
                continue;
            }

            $tenant->run(function () use ($tenant): void {
                $sent = app(ScheduledBookingMessages::class)->sendDue();

                $this->info("Sent {$sent} scheduled booking messages for tenant: {$tenant->getTenantKey()}");
            });
        }

        return Command::SUCCESS;
    }
}

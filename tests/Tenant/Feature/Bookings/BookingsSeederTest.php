<?php

use App\Enums\BookingType;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceCategory;
use App\Models\Bookings\ServiceOption;
use App\Models\Crm\OrganisationUnit;
use Database\Seeders\BookingsSeeder;

it('seeds a service of every booking type, and is safe to re-run', function () {
    OrganisationUnit::factory()->location()->create();

    $this->seed(BookingsSeeder::class);
    $this->seed(BookingsSeeder::class);

    expect(Service::query()->pluck('booking_type')->unique()->count())->toBe(count(BookingType::cases()))
        ->and(Service::query()->count())->toBe(7)
        ->and(ServiceCategory::query()->count())->toBe(3)
        ->and(ServiceOption::query()->count())->toBe(3)
        ->and(Service::query()->firstWhere('slug', 'room-hire')->locations()->count())->toBe(1);
});

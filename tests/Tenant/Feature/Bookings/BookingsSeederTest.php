<?php

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\ResourceType;
use App\Enums\BookingType;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\MessageLog;
use App\Models\Bookings\MessageTemplate;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceCategory;
use App\Models\Bookings\ServiceOption;
use App\Models\Bookings\ServiceSession;
use App\Models\Bookings\Shift;
use App\Models\Bookings\TimeOff;
use App\Models\Bookings\WaitlistEntry;
use App\Models\Bookings\WorkingHour;
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
        ->and(Service::query()->firstWhere('slug', 'room-hire')->locations()->count())->toBe(1)
        ->and(BookableResource::query()->pluck('type')->unique()->count())->toBe(count(ResourceType::cases()))
        ->and(BookableResource::query()->count())->toBe(7)
        ->and(BookableResource::query()->where('combine_group', 'Window')->count())->toBe(2)
        ->and(WorkingHour::query()->count())->toBe(25)
        ->and(Shift::query()->count())->toBe(1)
        ->and(TimeOff::query()->count())->toBe(3)
        ->and(ServiceSession::query()->count())->toBe(2)
        ->and(Booking::query()->count())->toBe(12)
        ->and(Booking::query()->whereNotNull('series_id')->count())->toBe(3)
        ->and(WaitlistEntry::query()->count())->toBe(1)
        ->and(MessageTemplate::query()->count())->toBe(2)
        ->and(MessageLog::query()->count())->toBe(5)
        ->and(Booking::query()->where('is_walk_in', true)->count())->toBe(1)
        ->and(Booking::query()->where('status', BookingStatus::Cancelled)->count())->toBe(1)
        ->and(Booking::query()->whereNotNull('cancelled_at')->count())->toBe(1)
        ->and(Booking::query()->whereNull('starts_at')->count())->toBe(1)
        ->and(BookableResource::query()->firstWhere('slug', 'sam-taylor')->services()->count())->toBe(2)
        ->and(BookableResource::query()->firstWhere('slug', 'sam-taylor')->services()->firstWhere('slug', 'consultation')->pivot->price)->toBe(5000);
});

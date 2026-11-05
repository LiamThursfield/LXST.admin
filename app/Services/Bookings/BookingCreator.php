<?php

namespace App\Services\Bookings;

use App\Enums\Bookings\BookingStatus;
use App\Enums\Bookings\MessageChannel;
use App\Enums\BookingType;
use App\Enums\TenantModule;
use App\Models\Bookings\BookableResource;
use App\Models\Bookings\Booking;
use App\Models\Bookings\Service;
use App\Models\Bookings\ServiceOption;
use App\Models\Bookings\ServiceSession;
use App\Models\Crm\Contact;
use App\Models\Crm\OrganisationUnit;
use App\Models\User;
use App\Services\Industry\Terminology;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Saves bookings. The time is checked again inside a transaction, holding a lock on what's being
 * booked, so two people can't take the same time however close together they ask.
 */
class BookingCreator
{
    /** @var list<int> The resources chosen for the booking being made */
    protected array $pendingResourceIds = [];

    public function __construct(
        protected BookingAvailability $availability,
        protected BookingRules $rules,
    ) {}

    /**
     * Books the same time every few weeks, as a linked series. The first has to fit, as it's what was
     * asked for; after that, the dates that fit are booked and the rest are given back to sort out.
     *
     * @param  array{date: string, time: string, ignore_rules?: bool|null, quantity?: int|null, option_ids?: list<int>, resource_ids?: list<int>, customer_name: string, customer_email?: string|null, customer_phone?: string|null, notes?: string|null}  $details
     * @return array{booked: list<Booking>, skipped: list<string>}
     *
     * @throws ValidationException When the first doesn't fit
     */
    public function createSeries(Service $service, ?OrganisationUnit $location, array $details, int $everyWeeks, int $times, ?User $createdBy = null): array
    {
        $seriesId = (string) Str::uuid();
        $first = CarbonImmutable::parse($details['date']);
        $booked = [$this->create($service, $location, [...$details, 'series_id' => $seriesId], $createdBy)];
        $skipped = [];

        for ($occurrence = 1; $occurrence < $times; $occurrence++) {
            $date = $first->addWeeks($occurrence * $everyWeeks)->toDateString();

            try {
                $booked[] = $this->create($service, $location, [...$details, 'date' => $date, 'series_id' => $seriesId], $createdBy);
            } catch (ValidationException) {
                $skipped[] = $date;
            }
        }

        return ['booked' => $booked, 'skipped' => $skipped];
    }

    /**
     * Cancels a booking, noting when it's inside the service's cancellation window
     */
    public function cancel(Booking $booking): void
    {
        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'is_late_cancellation' => $this->rules->isLateToCancel($booking),
        ]);
    }

    /**
     * A walk-in starts now, so skips the rule that a booking has to be in the future, and is
     * checked in straight away.
     *
     * The service's rules are kept unless ignore_rules is set, which staff can choose. Approval is
     * only for bookings customers make, as staff confirm their own.
     *
     * @param  array{date?: string|null, time?: string|null, walk_in?: bool|null, ignore_rules?: bool|null, series_id?: string|null, channel?: string|null, session_id?: int|null, quantity?: int|null, option_ids?: list<int>, resource_ids?: list<int>, customer_name: string, customer_email?: string|null, customer_phone?: string|null, notes?: string|null}  $details
     *
     * @throws ValidationException When the time or places have gone
     */
    public function create(Service $service, ?OrganisationUnit $location, array $details, ?User $createdBy = null, bool $byCustomer = false): Booking
    {
        return DB::transaction(function () use ($service, $location, $details, $createdBy, $byCustomer) {
            $quantity = max(1, (int) ($details['quantity'] ?? 1));
            /** @var Collection<int, ServiceOption> $options */
            $options = $service->options()->whereKey($details['option_ids'] ?? [])->get();

            $booking = match (true) {
                $service->booking_type === BookingType::Enquiry => $this->newEnquiry($service, $location, $quantity, $options),
                $service->booking_type->usesSessions() => $this->newOnSession($service, (int) ($details['session_id'] ?? 0), $quantity, $options),
                default => $this->newAtTime($service, $location, $details, $quantity, $options),
            };

            $booking->fill([
                'customer_name' => $details['customer_name'],
                'customer_email' => $details['customer_email'] ?? null,
                'customer_phone' => $details['customer_phone'] ?? null,
                'notes' => $details['notes'] ?? null,
                'created_by_id' => $createdBy?->id,
                'rules_ignored' => (bool) ($details['ignore_rules'] ?? false),
                'series_id' => $details['series_id'] ?? null,
            ]);

            if (! $booking->rules_ignored && ($problems = $this->rules->problems($service, $booking)) !== []) {
                throw ValidationException::withMessages($problems);
            }

            if ($byCustomer && $service->requires_approval && $booking->status === BookingStatus::Confirmed) {
                $booking->status = BookingStatus::Requested;
            }

            $booking->contact_id = $this->contactIdFor($details);
            $booking->channel = MessageChannel::tryFrom((string) ($details['channel'] ?? ''))
                ?? Contact::query()->find($booking->contact_id)->preferred_channel
                ?? MessageChannel::Email;
            $booking->save();

            $booking->resources()->sync($this->pendingResourceIds);

            $booking->options()->createMany($options->map(fn (ServiceOption $option) => [
                'service_option_id' => $option->id,
                'type' => $option->type,
                'name' => $option->name,
                'price_adjustment' => $option->price_adjustment,
                'duration_adjustment' => $option->duration_adjustment,
            ])->all());

            return $booking;
        });
    }

    /**
     * Sets the time of an enquiry, making it a confirmed booking. The work behind an enquiry can be
     * out of hours, so only clashes with other bookings are refused.
     *
     * @param  list<int>  $resourceIds
     *
     * @throws ValidationException When a resource is taken
     */
    public function schedule(Booking $booking, string $date, string $time, int $durationMinutes, array $resourceIds): void
    {
        DB::transaction(function () use ($booking, $date, $time, $durationMinutes, $resourceIds) {
            $this->lockResources($resourceIds);

            $start = CarbonImmutable::parse("{$date} {$time}", $booking->timezone);
            $end = $start->addMinutes($durationMinutes);

            if (! $this->availability->isFree($resourceIds, $start, $end)) {
                throw ValidationException::withMessages(['time' => __('bookings.bookings.validation.time_taken')]);
            }

            $booking->update([
                'status' => BookingStatus::Confirmed,
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'blocked_from' => $start->utc(),
                'blocked_until' => $end->utc(),
            ]);
            $booking->resources()->sync($resourceIds);
        });
    }

    /**
     * Moves a booking to another time, keeping its length and buffers, and optionally swaps one of its
     * resources for another of the same type. Only clashes are refused: someone moving a booking by
     * hand may mean it to be out of hours.
     *
     * @throws ValidationException When it can't be moved there
     */
    public function move(Booking $booking, string $date, string $time, ?int $resourceId = null): void
    {
        if (! $booking->isMovable() || $booking->starts_at === null || $booking->ends_at === null) {
            throw ValidationException::withMessages(['booking' => app(Terminology::class)->translate('bookings.bookings.validation.not_movable')]);
        }

        DB::transaction(function () use ($booking, $date, $time, $resourceId) {
            $resourceIds = array_values($booking->resources()->pluck('bookings_resources.id')->map(intval(...))->all());

            if ($resourceId !== null && ! in_array($resourceId, $resourceIds, true)) {
                $resourceIds = $this->swapResource($booking, $resourceIds, $resourceId);
            }

            $this->lockResources(array_values(array_unique([...$resourceIds, ...$booking->resources()->pluck('bookings_resources.id')->map(intval(...))->all()])));

            $start = CarbonImmutable::parse("{$date} {$time}", $booking->timezone);
            $end = $start->addSeconds((int) $booking->starts_at->diffInSeconds($booking->ends_at));
            $blockedFrom = $start->subSeconds((int) ($booking->blocked_from ?? $booking->starts_at)->diffInSeconds($booking->starts_at));
            $blockedUntil = $end->addSeconds((int) $booking->ends_at->diffInSeconds($booking->blocked_until ?? $booking->ends_at));

            if (! $this->availability->isFree($resourceIds, $blockedFrom, $blockedUntil, exceptBookingId: $booking->id)) {
                throw ValidationException::withMessages(['time' => __('bookings.bookings.validation.time_taken')]);
            }

            $booking->update([
                'starts_at' => $start->utc(),
                'ends_at' => $end->utc(),
                'blocked_from' => $blockedFrom->utc(),
                'blocked_until' => $blockedUntil->utc(),
            ]);
            $booking->resources()->sync($resourceIds);
        });
    }

    /**
     * Replaces the booking's resource of the new one's type, which has to be able to do the service
     *
     * @param  list<int>  $resourceIds
     * @return list<int>
     */
    protected function swapResource(Booking $booking, array $resourceIds, int $resourceId): array
    {
        $resource = $booking->service?->resources()->where('is_active', true)->find($resourceId);
        $replaced = $resource === null ? null : $booking->resources->first(fn (BookableResource $current) => $current->type === $resource->type);

        if ($resource === null || $replaced === null) {
            throw ValidationException::withMessages(['resource_id' => app(Terminology::class)->translate('bookings.bookings.validation.cannot_move_to_resource')]);
        }

        return array_map(fn (int $id) => $id === $replaced->id ? $resource->id : $id, $resourceIds);
    }

    /**
     * @param  Collection<int, ServiceOption>  $options
     */
    protected function newEnquiry(Service $service, ?OrganisationUnit $location, int $quantity, Collection $options): Booking
    {
        return $this->newBooking($service, $location?->id, BookingStatus::Requested, $this->availability->timezoneFor($location), $quantity, $options, []);
    }

    /**
     * @param  Collection<int, ServiceOption>  $options
     */
    protected function newOnSession(Service $service, int $sessionId, int $quantity, Collection $options): Booking
    {
        $session = ServiceSession::query()->where('service_id', $service->id)->lockForUpdate()->find($sessionId);

        if ($session === null || $session->starts_at->isPast() || $this->availability->placesLeft($session) < $quantity) {
            throw ValidationException::withMessages(['session_id' => __('bookings.bookings.validation.no_places')]);
        }

        $location = $session->location;
        $booking = $this->newBooking($service, $session->location_id, BookingStatus::Confirmed, $this->availability->timezoneFor($location), $quantity, $options, []);

        $booking->fill([
            'session_id' => $session->id,
            'starts_at' => $session->starts_at,
            'ends_at' => $session->ends_at,
            'blocked_from' => $session->starts_at,
            'blocked_until' => $session->ends_at,
        ]);

        return $booking;
    }

    /**
     * @param  array{date?: string|null, time?: string|null, walk_in?: bool|null, resource_ids?: list<int>}  $details
     * @param  Collection<int, ServiceOption>  $options
     */
    protected function newAtTime(Service $service, ?OrganisationUnit $location, array $details, int $quantity, Collection $options): Booking
    {
        $this->lockResources(array_values(array_map(fn (BookableResource $resource) => $resource->id, $service->resources()->get()->all())));

        $timezone = $this->availability->timezoneFor($location);
        $walkIn = (bool) ($details['walk_in'] ?? false);
        $start = $walkIn
            ? CarbonImmutable::now($timezone)->startOfMinute()
            : CarbonImmutable::parse(($details['date'] ?? '').' '.($details['time'] ?? ''), $timezone);
        $slot = $walkIn || $start->isFuture()
            ? $this->availability->slotAt($service, $location, $start, $quantity, array_values(array_map(fn (ServiceOption $option) => $option->id, $options->all())), $details['resource_ids'] ?? [])
            : null;

        if ($slot === null) {
            throw ValidationException::withMessages([
                'time' => __($walkIn ? 'bookings.bookings.validation.nobody_free_now' : 'bookings.bookings.validation.time_taken'),
            ]);
        }

        $booking = $this->newBooking($service, $location?->id, $walkIn ? BookingStatus::CheckedIn : BookingStatus::Confirmed, $timezone, $quantity, $options, $slot->resources);

        $booking->fill([
            'is_walk_in' => $walkIn,
            'starts_at' => $slot->startsAt->utc(),
            'ends_at' => $slot->endsAt->utc(),
            'blocked_from' => $slot->blockedFrom->utc(),
            'blocked_until' => $slot->blockedUntil->utc(),
        ]);

        return $booking;
    }

    /**
     * @param  Collection<int, ServiceOption>  $options
     * @param  list<BookableResource>  $resources
     */
    protected function newBooking(Service $service, ?int $locationId, BookingStatus $status, string $timezone, int $quantity, Collection $options, array $resources): Booking
    {
        $booking = new Booking([
            'service_id' => $service->id,
            'location_id' => $locationId,
            'status' => $status,
            'timezone' => $timezone,
            'quantity' => $quantity,
            'service_name' => $service->name,
            'price_type' => $service->price_type,
            'price' => $this->priceFor($service, $resources, $options, $quantity),
        ]);

        $this->pendingResourceIds = array_map(fn (BookableResource $resource) => $resource->id, $resources);

        return $booking;
    }

    /**
     * The total in minor units, or null on quote. A resource's own price beats the service's, options
     * add to it, and classes, tickets and hires are per place or unit.
     *
     * @param  list<BookableResource>  $resources
     * @param  Collection<int, ServiceOption>  $options
     */
    protected function priceFor(Service $service, array $resources, Collection $options, int $quantity): ?int
    {
        if (! $service->price_type->hasAmount()) {
            return null;
        }

        $base = $service->price ?? 0;

        foreach ($resources as $resource) {
            /** @var object{price: int|null}|null $pivot */
            $pivot = $resource->getRelationValue('pivot');

            if ($pivot?->price !== null) {
                $base = $pivot->price;

                break;
            }
        }

        $each = max(0, $base + (int) $options->sum('price_adjustment'));

        return $service->booking_type->pricesPerUnit() ? $each * $quantity : $each;
    }

    /**
     * Locks in id order, so two bookings can't each hold what the other is waiting for
     *
     * @param  list<int>  $resourceIds
     */
    protected function lockResources(array $resourceIds): void
    {
        BookableResource::query()->whereKey($resourceIds)->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * The CRM contact with this email, added if new, when the tenant has the CRM. A deleted contact
     * stays deleted, and the booking keeps the details on its own.
     *
     * @param  array{customer_name: string, customer_email?: string|null, customer_phone?: string|null}  $details
     */
    protected function contactIdFor(array $details): ?int
    {
        $email = $details['customer_email'] ?? null;

        if (blank($email) || ! (tenant()?->hasModule(TenantModule::Crm) ?? false)) {
            return null;
        }

        $contact = Contact::withTrashed()->firstWhere('email', $email);

        if ($contact !== null) {
            return $contact->trashed() ? null : $contact->id;
        }

        return Contact::query()->create([
            'first_name' => Str::before($details['customer_name'], ' '),
            'last_name' => Str::contains($details['customer_name'], ' ') ? Str::after($details['customer_name'], ' ') : null,
            'email' => $email,
            'telephone' => $details['customer_phone'] ?? null,
        ])->id;
    }
}

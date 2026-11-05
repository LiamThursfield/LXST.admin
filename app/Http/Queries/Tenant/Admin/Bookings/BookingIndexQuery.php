<?php

namespace App\Http\Queries\Tenant\Admin\Bookings;

use App\Http\Requests\Tenant\Admin\Bookings\BookingIndexRequest;
use App\Models\Bookings\Booking;
use Illuminate\Pagination\LengthAwarePaginator;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class BookingIndexQuery
{
    /**
     * Enquiries still waiting for a time come first, so they aren't lost at the end of the list
     *
     * @return LengthAwarePaginator<int, Booking>
     */
    public function handle(BookingIndexRequest $request): LengthAwarePaginator
    {
        return QueryBuilder::for(Booking::query()->with(['resources', 'location'])->orderByRaw('starts_at is null desc'), $request)
            ->allowedFilters(
                'customer_name',
                AllowedFilter::exact('status'),
            )
            ->defaultSort('-starts_at')
            ->allowedSorts(
                'starts_at',
                'created_at',
            )
            ->paginate($request->input('per_page', 15))
            ->appends($request->query());
    }
}

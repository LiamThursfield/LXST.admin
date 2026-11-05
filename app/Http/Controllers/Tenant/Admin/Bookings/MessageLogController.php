<?php

namespace App\Http\Controllers\Tenant\Admin\Bookings;

use App\Enums\Bookings\MessageStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Bookings\MessageLogIndexRequest;
use App\Models\Bookings\MessageLog;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Every booking message: who it went to, how, and whether it got there
 */
class MessageLogController extends Controller
{
    public function __invoke(MessageLogIndexRequest $request): Response
    {
        $logs = QueryBuilder::for(MessageLog::query()->with('booking'), $request)
            ->allowedFilters(AllowedFilter::exact('status'))
            ->defaultSort('-id')
            ->paginate($request->integer('per_page', 15))
            ->appends($request->query());

        return Inertia::render('admin/bookings/message-log/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');

                return $validated == null ? null : collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'when', 'header' => __('bookings.messages.log.columns.when')],
                ['accessorKey' => 'customer_name', 'header' => __('bookings.messages.log.columns.customer')],
                ['accessorKey' => 'event_label', 'header' => __('bookings.messages.log.columns.event')],
                ['accessorKey' => 'channel_label', 'header' => __('bookings.messages.log.columns.channel')],
                ['accessorKey' => 'recipient', 'header' => __('bookings.messages.log.columns.recipient')],
                ['accessorKey' => 'status_label', 'header' => __('bookings.messages.log.columns.status')],
                ['accessorKey' => 'note', 'header' => __('bookings.messages.log.columns.note')],
            ],
            'logs' => [
                'data' => $logs->getCollection()->map(fn (MessageLog $log) => [
                    'id' => $log->id,
                    'when' => $log->created_at?->setTimezone($log->booking->timezone ?? (string) config('app.timezone'))->format('D j M Y, H:i'),
                    'customer_name' => $log->booking?->customer_name,
                    'booking_url' => $log->booking_id === null ? null : route('admin.bookings.bookings.show', $log->booking_id),
                    'event_label' => $log->event->label(),
                    'channel_label' => $log->channel->label(),
                    'recipient' => $log->recipient,
                    'status' => $log->status->value,
                    'status_label' => $log->status->label(),
                    'note' => $log->note,
                ])->values(),
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'total' => $logs->total(),
                    'per_page' => $logs->perPage(),
                ],
            ],
            'statuses' => MessageStatus::asSelectItems(),
        ]);
    }
}

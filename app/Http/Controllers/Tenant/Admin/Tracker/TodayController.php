<?php

namespace App\Http\Controllers\Tenant\Admin\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Tracker\TodayQuery;
use App\Http\Requests\Tenant\Admin\Tracker\TodayRequest;
use App\Http\Resources\Tenant\Admin\Tracker\TaskOccurrenceResource;
use App\Models\User;
use App\Services\Authorisation\Enums\TrackerPermission;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function __invoke(TodayRequest $request, TodayQuery $query): Response
    {
        /** @var User $user */
        $user = $request->user();
        $day = $request->day();

        return Inertia::render('admin/tracker/today/Index', [
            'date' => $day->toDateString(),
            'today' => $user->localToday()->toDateString(),
            'occurrences' => TaskOccurrenceResource::collection($query->handle($user, $day)),
            'canLog' => $user->can(TrackerPermission::LogEntries),
        ]);
    }
}

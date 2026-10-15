<?php

namespace App\Http\Controllers\Tenant\Admin\Tracker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Tracker\TaskEntryStoreRequest;
use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Services\Tracker\EntryRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class TaskEntryController extends Controller
{
    public function __construct(protected EntryRecorder $recorder) {}

    public function store(TaskEntryStoreRequest $request, TaskDefinition $task): RedirectResponse
    {
        $occurredAt = $request->filled('occurred_at')
            ? CarbonImmutable::parse($request->string('occurred_at')->value())
            : CarbonImmutable::now();

        $this->recorder->record(
            $task,
            (float) $request->input('value'),
            $occurredAt,
            $request->filled('label') ? $request->string('label')->value() : null,
        );

        return redirect()->back()->with('success', 'Progress logged.');
    }

    public function destroy(TaskEntry $entry): RedirectResponse
    {
        Gate::authorize('delete', $entry);

        $this->recorder->delete($entry);

        return redirect()->back()->with('success', 'Entry removed.');
    }
}

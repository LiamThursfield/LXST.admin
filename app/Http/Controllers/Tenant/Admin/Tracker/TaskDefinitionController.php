<?php

namespace App\Http\Controllers\Tenant\Admin\Tracker;

use App\Data\Tracker\TaskSchedule;
use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TrackerAggregation;
use App\Enums\Tracker\TrackerComparator;
use App\Enums\Tracker\TrackerMeasure;
use App\Enums\Tracker\TrackerPeriod;
use App\Enums\Tracker\TrackerUnit;
use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Tracker\TaskDefinitionIndexQuery;
use App\Http\Requests\Tenant\Admin\Tracker\TaskDefinitionIndexRequest;
use App\Http\Requests\Tenant\Admin\Tracker\TaskDefinitionStoreRequest;
use App\Http\Requests\Tenant\Admin\Tracker\TaskDefinitionUpdateRequest;
use App\Http\Resources\Tenant\Admin\Tracker\TaskDefinitionResource;
use App\Models\Tracker\Category;
use App\Models\Tracker\TaskDefinition;
use App\Models\User;
use App\Services\Tracker\TaskDefinitionUpdater;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TaskDefinitionController extends Controller
{
    public function __construct(protected TaskDefinitionUpdater $updater) {}

    public function index(TaskDefinitionIndexRequest $request, TaskDefinitionIndexQuery $query): Response
    {
        return Inertia::render('admin/tracker/task/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter()->toArray();
            },
            'columns' => [
                ['accessorKey' => 'title', 'header' => __('tracker.tasks.columns.title')],
                ['accessorKey' => 'period', 'header' => __('tracker.tasks.columns.period')],
                ['accessorKey' => 'status', 'header' => __('tracker.tasks.columns.status')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'tasks' => TaskDefinitionResource::collection($query->handle($request)),
            'statuses' => TaskDefinitionStatus::asSelectItems(),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', TaskDefinition::class);

        return Inertia::render('admin/tracker/task/Create', $this->formOptions());
    }

    public function store(TaskDefinitionStoreRequest $request): RedirectResponse
    {
        TaskDefinition::create([
            ...$this->attributesFromRequest($request),
            'user_id' => $request->user()?->id,
        ]);

        return redirect()->route('admin.tracker.tasks.index')
            ->with('success', __('tracker.tasks.flash.created'));
    }

    public function show(TaskDefinition $task): Response
    {
        Gate::authorize('view', $task);

        return Inertia::render('admin/tracker/task/Show', [
            'task' => new TaskDefinitionResource($task->load('category')),
            ...$this->formOptions(),
        ]);
    }

    public function edit(TaskDefinition $task): Response
    {
        Gate::authorize('update', $task);

        return Inertia::render('admin/tracker/task/Edit', [
            'task' => new TaskDefinitionResource($task->load('category')),
            ...$this->formOptions(),
        ]);
    }

    public function update(
        TaskDefinitionUpdateRequest $request,
        TaskDefinition $task,
    ): RedirectResponse {
        $this->updater->update($task, $this->attributesFromRequest($request));

        return redirect()->route('admin.tracker.tasks.index')
            ->with('success', __('tracker.tasks.flash.updated'));
    }

    public function destroy(TaskDefinition $task): RedirectResponse
    {
        Gate::authorize('delete', $task);

        $task->delete();

        return redirect()->back()->with('success', __('tracker.tasks.flash.deleted'));
    }

    /**
     * Map a validated request into database-ready attributes, converting the
     * targets from their display unit into the canonical base unit.
     *
     * @return array<string, mixed>
     */
    protected function attributesFromRequest(FormRequest $request): array
    {
        $unit = TrackerUnit::from($request->string('unit')->value());

        $targetMin = $request->input('target_min');
        $targetMax = $request->input('target_max');

        /** @var array<string, mixed>|null $schedule */
        $schedule = $request->input('schedule');

        return [
            'category_id' => $request->input('category_id'),
            'title' => $request->string('title')->value(),
            'description' => $request->input('description'),
            'period' => $request->string('period')->value(),
            'schedule' => $schedule !== null ? TaskSchedule::from($schedule) : null,
            'measure' => $request->string('measure')->value(),
            'unit' => $unit->value,
            'comparator' => $request->string('comparator')->value(),
            'aggregation' => $request->string('aggregation')->value(),
            'target_min' => $targetMin !== null ? $unit->toBase((float) $targetMin) : null,
            'target_max' => $targetMax !== null ? $unit->toBase((float) $targetMax) : null,
            'stepped' => $request->boolean('stepped'),
            'counts_during_holiday' => $request->boolean('counts_during_holiday'),
            'active_from' => $request->date('active_from'),
            'active_to' => $request->date('active_to'),
            'status' => $request->string('status')->value(),
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }

    /**
     * The shared select options passed to the create/edit/show forms.
     *
     * @return array<string, mixed>
     */
    protected function formOptions(): array
    {
        /** @var User $user */
        $user = request()->user();

        return [
            'categories' => Category::query()
                ->visibleTo($user)
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category) => [
                    'value' => $category->id,
                    'label' => $category->name,
                ])
                ->all(),
            'periods' => TrackerPeriod::asSelectItems(),
            'measures' => TrackerMeasure::asSelectItems(),
            'units' => TrackerUnit::asSelectItems(),
            'comparators' => TrackerComparator::asSelectItems(),
            'aggregations' => TrackerAggregation::asSelectItems(),
            'statuses' => TaskDefinitionStatus::asSelectItems(),
        ];
    }
}

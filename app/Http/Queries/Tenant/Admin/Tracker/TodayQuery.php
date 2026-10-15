<?php

namespace App\Http\Queries\Tenant\Admin\Tracker;

use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use App\Services\Tracker\OccurrenceEvaluator;
use App\Services\Tracker\OccurrenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class TodayQuery
{
    public function __construct(
        protected OccurrenceGenerator $generator,
        protected OccurrenceEvaluator $evaluator,
    ) {}

    /**
     * The user's occurrences that cover the given day, one per applicable task.
     * Occurrences are created lazily and evaluated so the day is accurate even
     * before the nightly generation/evaluation runs.
     *
     * @return Collection<int, TaskOccurrence>
     */
    public function handle(User $user, CarbonImmutable $day): Collection
    {
        return TaskDefinition::ownedBy($user)
            ->applicableOn($day)
            ->with('category')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(function (TaskDefinition $definition) use ($day): ?TaskOccurrence {
                $occurrence = $this->generator->ensureOccurrence($definition, $day);

                if ($occurrence === null) {
                    return null;
                }

                $occurrence->setRelation('taskDefinition', $definition);

                return $this->evaluator->evaluate($occurrence)->load('entries');
            })
            ->filter()
            ->values();
    }
}

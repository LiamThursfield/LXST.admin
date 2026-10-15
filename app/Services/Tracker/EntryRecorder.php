<?php

namespace App\Services\Tracker;

use App\Models\Tracker\TaskDefinition;
use App\Models\Tracker\TaskEntry;
use App\Models\Tracker\TaskOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records and removes progress entries, keeping the affected occurrence's
 * evaluation up to date. Entries may be backfilled for past dates, which can
 * change a closed occurrence's outcome (e.g. Missed to Met).
 */
class EntryRecorder
{
    public function __construct(
        private OccurrenceGenerator $generator,
        private OccurrenceEvaluator $evaluator,
    ) {}

    /**
     * Log a value (in the definition's display unit) at the given moment.
     * For non-stepped definitions the new entry replaces any existing one.
     *
     * The occurrence is chosen from the owner's local calendar day and bound
     * by foreign key, so it never moves if the owner later changes timezone.
     *
     * @throws ValidationException when the task is not scheduled for that date
     */
    public function record(TaskDefinition $definition, float $displayValue, CarbonImmutable $occurredAt, ?string $label = null): TaskEntry
    {
        /** @var User $owner */
        $owner = $definition->user;
        $occurredAt = $occurredAt->setTimezone((string) config('app.timezone'));

        $occurrence = $this->generator->ensureOccurrence($definition, $owner->localDate($occurredAt));

        if ($occurrence === null) {
            throw ValidationException::withMessages([
                'occurred_at' => __('tracker.validation.not_scheduled'),
            ]);
        }

        return DB::transaction(function () use ($definition, $occurrence, $displayValue, $occurredAt, $label): TaskEntry {
            if (! $definition->stepped) {
                $occurrence->entries()->delete();
            }

            $entry = $occurrence->entries()->create([
                'user_id' => $occurrence->user_id,
                'value' => $definition->unit->toBase($displayValue),
                'label' => $label,
                'occurred_at' => $occurredAt,
            ]);

            $this->evaluator->evaluate($occurrence);

            return $entry;
        });
    }

    /**
     * Remove an entry and re-evaluate the occurrence it belonged to.
     */
    public function delete(TaskEntry $entry): void
    {
        DB::transaction(function () use ($entry): void {
            /** @var TaskOccurrence $occurrence */
            $occurrence = $entry->taskOccurrence;

            $entry->delete();

            $this->evaluator->evaluate($occurrence);
        });
    }
}

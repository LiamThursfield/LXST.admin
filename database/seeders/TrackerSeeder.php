<?php

namespace Database\Seeders;

use App\Data\Tracker\TaskSchedule;
use App\Enums\Tracker\TaskDefinitionStatus;
use App\Enums\Tracker\TrackerAggregation;
use App\Enums\Tracker\TrackerComparator;
use App\Enums\Tracker\TrackerPeriod;
use App\Enums\Tracker\TrackerUnit;
use App\Models\Tracker\Category;
use App\Models\Tracker\Holiday;
use App\Models\Tracker\TaskDefinition;
use App\Models\User;
use App\Services\Tracker\EntryRecorder;
use App\Services\Tracker\OccurrenceEvaluator;
use App\Services\Tracker\OccurrenceGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Seeds the tracker's example habits for a user, with four weeks of realistic
 * history (including a short holiday) and a partly-logged "today".
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=TrackerSeeder`.
 * Seeds the given user, or the tenant's first user. Safe to re-run: a user who
 * already has tasks is skipped.
 */
class TrackerSeeder extends Seeder
{
    /**
     * How many days of history to generate before today.
     */
    private const HISTORY_DAYS = 27;

    /**
     * Fixed seed so the generated history is the same on every run.
     */
    private const RANDOM_SEED = 2026;

    private Randomizer $random;

    public function __construct(
        private EntryRecorder $recorder,
        private OccurrenceGenerator $generator,
        private OccurrenceEvaluator $evaluator,
    ) {
        $this->random = new Randomizer(new Mt19937(self::RANDOM_SEED));
    }

    /**
     * Run the database seeds.
     */
    public function run(?User $user = null): void
    {
        $user ??= User::query()->oldest('id')->first() ?? User::factory()->create([
            'first_name' => 'Demo',
            'last_name' => 'User',
            'email' => 'demo@example.com',
        ]);

        $categories = $this->seedCategories();

        if (TaskDefinition::ownedBy($user)->exists()) {
            $this->command?->warn("Tracker data already exists for {$user->email}; skipping.");

            return;
        }

        $today = CarbonImmutable::today();
        $start = $today->subDays(self::HISTORY_DAYS);

        $tasks = $this->seedDefinitions($user, $categories, $start);

        $holiday = Holiday::create([
            'user_id' => $user->id,
            'label' => 'Weekend away',
            'starts_on' => $today->subDays(12),
            'ends_on' => $today->subDays(10),
        ]);

        for ($day = $start; $day->lessThanOrEqualTo($today); $day = $day->addDay()) {
            $onHoliday = $day->betweenIncluded($holiday->starts_on, $holiday->ends_on);

            $this->seedDay($tasks, $day, $day->equalTo($today), $onHoliday);
        }

        // Run the normal daily jobs to fill unlogged days and close finished ones.
        foreach ($tasks as $task) {
            $this->generator->generateForDefinition($task, $today);
        }

        $this->evaluator->evaluateDueForTenant($today);

        $this->command?->info("Seeded tracker data for {$user->email}.");
    }

    /**
     * Create the shared, public categories (once per tenant).
     *
     * @return array<string, Category>
     */
    private function seedCategories(): array
    {
        $categories = [
            'Health' => ['icon' => 'i-lucide-heart', 'colour' => '#e11d48'],
            'Fitness' => ['icon' => 'i-lucide-dumbbell', 'colour' => '#2563eb'],
            'Productivity' => ['icon' => 'i-lucide-list-checks', 'colour' => '#16a34a'],
            'Wellbeing' => ['icon' => 'i-lucide-smile', 'colour' => '#9333ea'],
        ];

        $models = [];

        foreach ($categories as $name => $attributes) {
            $models[$name] = Category::firstOrCreate(
                ['user_id' => null, 'is_public' => true, 'name' => $name],
                $attributes,
            );
        }

        return $models;
    }

    /**
     * Create the example habits. Targets are in each unit's base.
     *
     * @param  array<string, Category>  $categories
     * @return array<string, TaskDefinition>
     */
    private function seedDefinitions(User $user, array $categories, CarbonImmutable $start): array
    {
        $daily = [
            'user_id' => $user->id,
            'period' => TrackerPeriod::Daily,
            'active_from' => $start,
            'status' => TaskDefinitionStatus::Active,
            'counts_during_holiday' => false,
        ];

        $boolean = [
            'unit' => TrackerUnit::Boolean,
            'measure' => TrackerUnit::Boolean->measure(),
            'comparator' => TrackerComparator::Boolean,
            'aggregation' => TrackerAggregation::Any,
            'stepped' => false,
        ];

        $definitions = [
            'wake' => [
                ...$daily,
                'title' => 'Wake up by 6:30am',
                'category_id' => $categories['Wellbeing']->id,
                'unit' => TrackerUnit::TimeOfDay,
                'measure' => TrackerUnit::TimeOfDay->measure(),
                'comparator' => TrackerComparator::Max,
                'aggregation' => TrackerAggregation::Last,
                'target_max' => 6 * 3600 + 30 * 60,
                'stepped' => false,
                'sort_order' => 1,
            ],
            'water' => [
                ...$daily,
                'title' => 'Drink at least 2 litres of water',
                'category_id' => $categories['Health']->id,
                'unit' => TrackerUnit::Litres,
                'measure' => TrackerUnit::Litres->measure(),
                'comparator' => TrackerComparator::Min,
                'aggregation' => TrackerAggregation::Sum,
                'target_min' => TrackerUnit::Litres->toBase(2),
                'stepped' => true,
                'sort_order' => 2,
            ],
            'calories' => [
                ...$daily,
                'title' => 'Eat between 1500 and 1750 calories',
                'category_id' => $categories['Health']->id,
                'unit' => TrackerUnit::Kilocalories,
                'measure' => TrackerUnit::Kilocalories->measure(),
                'comparator' => TrackerComparator::Between,
                'aggregation' => TrackerAggregation::Sum,
                'target_min' => 1500,
                'target_max' => 1750,
                'stepped' => true,
                'sort_order' => 3,
            ],
            'snacks' => [
                ...$daily,
                ...$boolean,
                'title' => "Don't eat snacks",
                'category_id' => $categories['Health']->id,
                'sort_order' => 4,
            ],
            'workout' => [
                ...$daily,
                ...$boolean,
                'title' => 'Workout',
                'category_id' => $categories['Fitness']->id,
                'schedule' => TaskSchedule::from(['daysOfWeek' => [1, 3, 5]]),
                'sort_order' => 5,
            ],
            'chores' => [
                ...$daily,
                'title' => 'Do chores for at least 30 minutes',
                'category_id' => $categories['Productivity']->id,
                'unit' => TrackerUnit::Minutes,
                'measure' => TrackerUnit::Minutes->measure(),
                'comparator' => TrackerComparator::Min,
                'aggregation' => TrackerAggregation::Sum,
                'target_min' => TrackerUnit::Minutes->toBase(30),
                'stepped' => true,
                'sort_order' => 6,
            ],
            'social' => [
                ...$daily,
                'title' => 'Max 30 minutes of social media',
                'category_id' => $categories['Wellbeing']->id,
                'unit' => TrackerUnit::Minutes,
                'measure' => TrackerUnit::Minutes->measure(),
                'comparator' => TrackerComparator::Max,
                'aggregation' => TrackerAggregation::Sum,
                'target_max' => TrackerUnit::Minutes->toBase(30),
                'stepped' => true,
                'sort_order' => 7,
            ],
            'weight' => [
                ...$daily,
                'title' => 'Current weight',
                'category_id' => $categories['Fitness']->id,
                'period' => TrackerPeriod::Weekly,
                'unit' => TrackerUnit::Kilograms,
                'measure' => TrackerUnit::Kilograms->measure(),
                'comparator' => TrackerComparator::None,
                'aggregation' => TrackerAggregation::Last,
                'stepped' => false,
                // Still weighed in while away.
                'counts_during_holiday' => true,
                'sort_order' => 8,
            ],
        ];

        return array_map(
            fn (array $attributes): TaskDefinition => TaskDefinition::create($attributes),
            $definitions,
        );
    }

    /**
     * Log a plausible day of progress. Today only gets a partial morning.
     *
     * @param  array<string, TaskDefinition>  $tasks
     */
    private function seedDay(array $tasks, CarbonImmutable $day, bool $isToday, bool $onHoliday): void
    {
        $at = fn (int $hour, int $minute = 0): CarbonImmutable => $isToday
            ? CarbonImmutable::now()
            : $day->setTime($hour, $minute);

        // Weekly weigh-in on Mondays, trending gently down, even while away.
        if ($day->isMonday()) {
            $start = CarbonImmutable::today()->subDays(self::HISTORY_DAYS);
            $weeksIn = intdiv((int) $start->diffInDays($day), 7);
            $weight = 82.0 - $weeksIn * 0.3 + $this->random->getInt(-3, 3) / 10;

            $this->recorder->record($tasks['weight'], $weight, $at(7, 0));
        }

        if ($onHoliday) {
            return;
        }

        // Woke between 06:00 and 07:00, in 5-minute steps (seconds since midnight).
        $this->recorder->record($tasks['wake'], $this->random->getInt(72, 84) * 300, $at(7, 0));

        if ($isToday) {
            $this->recorder->record($tasks['water'], 0.5, $at(8, 0));
            $this->recorder->record($tasks['calories'], $this->random->getInt(350, 500), $at(8, 0), 'Breakfast');

            return;
        }

        foreach (range(1, $this->random->getInt(3, 7)) as $index) {
            $this->recorder->record($tasks['water'], $this->chance(50) ? 0.5 : 0.25, $at(7 + $index * 2, 0));
        }

        foreach (['Breakfast' => 8, 'Lunch' => 13, 'Dinner' => 19] as $meal => $hour) {
            $this->recorder->record($tasks['calories'], $this->random->getInt(400, 650), $at($hour, 0), $meal);
        }

        if ($this->chance(70)) {
            $this->recorder->record($tasks['snacks'], 1, $at(21, 0));
        }

        if (in_array($day->isoWeekday(), [1, 3, 5], true) && $this->chance(80)) {
            $this->recorder->record($tasks['workout'], 1, $at(18, 0));
        }

        foreach (array_slice([10, 16], 0, $this->random->getInt(1, 2)) as $hour) {
            $this->recorder->record($tasks['chores'], $this->random->getInt(10, 25), $at($hour, 0));
        }

        foreach (array_slice([12, 15, 20, 22], 0, $this->random->getInt(1, 4)) as $hour) {
            $this->recorder->record($tasks['social'], $this->random->getInt(5, 15), $at($hour, 0));
        }
    }

    /**
     * A percentage chance from the seeded generator.
     */
    private function chance(int $percent): bool
    {
        return $this->random->getInt(1, 100) <= $percent;
    }
}

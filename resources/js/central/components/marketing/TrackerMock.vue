<script setup lang="ts">
import MockWindow from './MockWindow.vue';

const { trans } = useTrans();

const nav = computed(() => [
    {
        label: trans('marketing.mock.goals'),
        icon: 'i-lucide-target',
        active: true,
    },
    { label: trans('marketing.mock.tasks'), icon: 'i-lucide-list-checks' },
]);

const days = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

/** Sample data: a week of goals, with each day done or not */
const goals = [
    {
        name: 'Walk 10,000 steps',
        progress: '5 of 7 days',
        week: [1, 1, 0, 1, 1, 1, 0],
    },
    {
        name: 'Drink 2 litres of water',
        progress: '6 of 7 days',
        week: [1, 1, 1, 1, 0, 1, 1],
    },
    {
        name: 'In bed by 23:00',
        progress: '3 of 7 days',
        week: [0, 1, 0, 1, 0, 0, 1],
    },
];

const targets = [
    { name: 'Books read this year', value: 42, target: 52 },
    { name: 'Weekly gym sessions', value: 3, target: 4 },
];
</script>

<template>
    <MockWindow
        business="My tracker"
        domain="tracker.example.com"
        :title="trans('marketing.mock.this_week')"
        :nav="nav"
    >
        <ul class="divide-default border-default divide-y rounded-lg border">
            <li
                v-for="goal in goals"
                :key="goal.name"
                class="flex items-center gap-3 px-3 py-2.5 text-sm"
            >
                <span class="min-w-0 flex-1">
                    <span class="text-highlighted block truncate font-medium">
                        {{ goal.name }}
                    </span>
                    <span class="text-muted block text-xs">
                        {{ goal.progress }}
                    </span>
                </span>
                <span class="flex shrink-0 gap-1">
                    <span
                        v-for="(done, day) in goal.week"
                        :key="day"
                        class="flex size-5 items-center justify-center rounded text-[10px] font-medium"
                        :class="
                            done
                                ? 'bg-primary text-inverted'
                                : 'bg-elevated text-dimmed'
                        "
                    >
                        {{ days[day] }}
                    </span>
                </span>
            </li>
        </ul>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <div
                v-for="target in targets"
                :key="target.name"
                class="border-default rounded-lg border px-3 py-2.5"
            >
                <p class="text-muted truncate text-xs">{{ target.name }}</p>
                <p
                    class="text-highlighted mt-1 text-lg font-semibold tabular-nums"
                >
                    {{ target.value }}
                    <span class="text-muted text-sm font-normal">
                        / {{ target.target }}
                    </span>
                </p>
                <div
                    class="bg-elevated mt-2 h-1.5 overflow-hidden rounded-full"
                >
                    <div
                        class="bg-primary h-full rounded-full"
                        :style="{
                            width: `${(target.value / target.target) * 100}%`,
                        }"
                    />
                </div>
            </div>
        </div>
    </MockWindow>
</template>

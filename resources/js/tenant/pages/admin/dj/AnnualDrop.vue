<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/AnnualDropController';

type Ranked = { plays: number };

const props = defineProps<{
    filter: {
        year: number | null;
        from: string | null;
        to: string | null;
        scope: 'gigs' | 'all';
    };
    period: { from: string; to: string };
    years: number[];
    timezone: string;
    drop: {
        totals: {
            plays: number;
            songs: number;
            gigs: number;
            hours: number;
            requests: number;
        };
        top_artists: Array<Ranked & { name: string }>;
        top_tracks: Array<Ranked & { title: string; artist: string | null }>;
        bpm: {
            most_played: number | null;
            most_played_count: number;
            range_from: number | null;
            range_to: number | null;
            range_count: number;
            average: number | null;
        };
        key: { camelot: string; label: string; plays: number } | null;
    };
}>();

const { trans, transChoice } = useTrans();
const numberFormat = new Intl.NumberFormat();

const thisYear = new Date().getFullYear();
const customRange = ref(props.filter.from !== null);
const filters = ref({
    year: props.filter.year ?? thisYear,
    from: props.filter.from ?? '',
    to: props.filter.to ?? '',
    scope: props.filter.scope,
});

const yearItems = computed(() =>
    [...new Set([thisYear, ...props.years])]
        .sort((first, second) => second - first)
        .map((year) => ({ value: year, label: String(year) })),
);
const scopeItems = (['gigs', 'all'] as const).map((value) => ({
    value,
    label: trans(`dj.annual_drop.scope.${value}`),
}));

function apply() {
    const query =
        customRange.value && filters.value.from && filters.value.to
            ? {
                  from: filters.value.from,
                  to: filters.value.to,
                  scope: filters.value.scope,
              }
            : { year: filters.value.year, scope: filters.value.scope };

    router.get(index.url(), query, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

watch(
    () => [filters.value.year, filters.value.scope],
    () => apply(),
);
watch(
    () => [filters.value.from, filters.value.to],
    () => customRange.value && apply(),
);
watch(customRange, (custom) => !custom && apply());

const dateFormat = (iso: string) =>
    new Date(iso).toLocaleDateString(undefined, {
        dateStyle: 'medium',
        timeZone: props.timezone,
    });

const heading = computed(() =>
    props.filter.from
        ? trans('dj.annual_drop.range_title', {
              from: dateFormat(props.period.from),
              to: dateFormat(props.period.to),
          })
        : trans('dj.annual_drop.year_title', {
              year: props.filter.year ?? thisYear,
          }),
);

// Ranked lists share one hue, scaled to the top entry
const share = (items: Ranked[], item: Ranked) =>
    `${(item.plays / Math.max(1, ...items.map((entry) => entry.plays))) * 100}%`;
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.annual_drop.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.annual_drop.title')" />

            <div class="mx-auto flex w-full max-w-5xl flex-col gap-6">
                <div class="flex flex-wrap items-end gap-3">
                    <USelect
                        v-if="!customRange"
                        v-model="filters.year"
                        :items="yearItems"
                        class="w-32"
                        :aria-label="trans('dj.annual_drop.fields.year')"
                    />
                    <template v-else>
                        <UFormField
                            :label="trans('dj.annual_drop.fields.from')"
                        >
                            <UInput v-model="filters.from" type="date" />
                        </UFormField>
                        <UFormField :label="trans('dj.annual_drop.fields.to')">
                            <UInput v-model="filters.to" type="date" />
                        </UFormField>
                    </template>
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :icon="
                            customRange
                                ? 'i-lucide-calendar'
                                : 'i-lucide-calendar-range'
                        "
                        :label="
                            customRange
                                ? trans('dj.annual_drop.by_year')
                                : trans('dj.annual_drop.custom_range')
                        "
                        @click="customRange = !customRange"
                    />
                    <USelect
                        v-model="filters.scope"
                        :items="scopeItems"
                        class="ms-auto w-40"
                    />
                </div>

                <div class="flex flex-col gap-1">
                    <p
                        class="text-primary text-sm font-semibold uppercase tracking-wide"
                    >
                        {{ trans('dj.annual_drop.title') }}
                    </p>
                    <h1 class="text-highlighted text-3xl font-bold sm:text-4xl">
                        {{ heading }}
                    </h1>
                    <p class="text-muted text-sm">
                        {{ trans('dj.annual_drop.description') }}
                    </p>
                </div>

                <p
                    v-if="!drop.totals.plays"
                    class="text-muted py-12 text-center"
                >
                    {{ trans('dj.annual_drop.nothing') }}
                </p>

                <template v-else>
                    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                        <UCard>
                            <p class="text-muted text-sm">
                                {{ trans('dj.annual_drop.totals.plays') }}
                            </p>
                            <p class="text-highlighted text-3xl font-bold">
                                {{ numberFormat.format(drop.totals.plays) }}
                            </p>
                            <p class="text-dimmed mt-1 text-xs">
                                {{
                                    transChoice(
                                        'dj.annual_drop.totals.songs',
                                        drop.totals.songs,
                                        {
                                            count: numberFormat.format(
                                                drop.totals.songs,
                                            ),
                                        },
                                    )
                                }}
                            </p>
                        </UCard>
                        <UCard>
                            <p class="text-muted text-sm">
                                {{ trans('dj.annual_drop.totals.hours') }}
                            </p>
                            <p class="text-highlighted text-3xl font-bold">
                                {{ numberFormat.format(drop.totals.hours) }}
                            </p>
                        </UCard>
                        <UCard>
                            <p class="text-muted text-sm">
                                {{ trans('dj.annual_drop.totals.gigs') }}
                            </p>
                            <p class="text-highlighted text-3xl font-bold">
                                {{ numberFormat.format(drop.totals.gigs) }}
                            </p>
                        </UCard>
                        <UCard>
                            <p class="text-muted text-sm">
                                {{ trans('dj.annual_drop.totals.requests') }}
                            </p>
                            <p class="text-highlighted text-3xl font-bold">
                                {{ numberFormat.format(drop.totals.requests) }}
                            </p>
                        </UCard>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <UCard>
                            <p class="text-muted text-sm">
                                {{ trans('dj.annual_drop.bpm') }}
                            </p>
                            <p class="text-highlighted text-2xl font-bold">
                                {{
                                    drop.bpm.most_played === null
                                        ? '–'
                                        : trans('dj.annual_drop.bpm_value', {
                                              bpm: drop.bpm.most_played,
                                          })
                                }}
                            </p>
                            <p
                                v-if="drop.bpm.average !== null"
                                class="text-dimmed mt-1 text-xs"
                            >
                                {{
                                    trans('dj.annual_drop.bpm_detail', {
                                        count: drop.bpm.most_played_count,
                                        average: drop.bpm.average,
                                    })
                                }}
                            </p>
                        </UCard>
                        <UCard>
                            <p class="text-muted text-sm">
                                {{ trans('dj.annual_drop.bpm_range') }}
                            </p>
                            <p class="text-highlighted text-2xl font-bold">
                                {{
                                    drop.bpm.range_from === null
                                        ? '–'
                                        : trans(
                                              'dj.annual_drop.bpm_range_value',
                                              {
                                                  from: drop.bpm.range_from,
                                                  to: drop.bpm.range_to ?? '',
                                              },
                                          )
                                }}
                            </p>
                            <p
                                v-if="drop.bpm.range_count"
                                class="text-dimmed mt-1 text-xs"
                            >
                                {{
                                    trans('dj.annual_drop.bpm_range_detail', {
                                        count: drop.bpm.range_count,
                                    })
                                }}
                            </p>
                        </UCard>
                        <UCard>
                            <p class="text-muted text-sm">
                                {{ trans('dj.annual_drop.key') }}
                            </p>
                            <p class="text-highlighted text-2xl font-bold">
                                {{ drop.key?.camelot ?? '–' }}
                            </p>
                            <p v-if="drop.key" class="text-dimmed mt-1 text-xs">
                                {{
                                    trans('dj.annual_drop.key_detail', {
                                        label: drop.key.label,
                                        count: drop.key.plays,
                                    })
                                }}
                            </p>
                        </UCard>
                    </div>

                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <UPageCard
                            :title="trans('dj.annual_drop.top_artists')"
                            variant="subtle"
                        >
                            <ol class="flex flex-col gap-3">
                                <li
                                    v-for="(
                                        artist, position
                                    ) in drop.top_artists"
                                    :key="artist.name"
                                    class="flex flex-col gap-1"
                                >
                                    <div
                                        class="flex items-baseline justify-between gap-3 text-sm"
                                    >
                                        <span
                                            class="text-highlighted min-w-0 truncate"
                                        >
                                            <span
                                                class="text-muted me-2 tabular-nums"
                                                >{{ position + 1 }}</span
                                            >{{ artist.name }}
                                        </span>
                                        <span
                                            class="text-muted shrink-0 tabular-nums"
                                        >
                                            {{
                                                transChoice(
                                                    'dj.annual_drop.plays',
                                                    artist.plays,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <span
                                        class="bg-elevated h-1.5 overflow-hidden rounded-full"
                                    >
                                        <span
                                            class="bg-primary block h-full rounded-full"
                                            :style="{
                                                width: share(
                                                    drop.top_artists,
                                                    artist,
                                                ),
                                            }"
                                        />
                                    </span>
                                </li>
                            </ol>
                        </UPageCard>

                        <UPageCard
                            :title="trans('dj.annual_drop.top_tracks')"
                            variant="subtle"
                        >
                            <ol class="flex flex-col gap-3">
                                <li
                                    v-for="(track, position) in drop.top_tracks"
                                    :key="position"
                                    class="flex flex-col gap-1"
                                >
                                    <div
                                        class="flex items-baseline justify-between gap-3 text-sm"
                                    >
                                        <span class="min-w-0 truncate">
                                            <span
                                                class="text-muted me-2 tabular-nums"
                                                >{{ position + 1 }}</span
                                            >
                                            <span class="text-highlighted">{{
                                                track.title
                                            }}</span>
                                            <span class="text-muted">
                                                ·
                                                {{
                                                    track.artist ??
                                                    trans(
                                                        'dj.annual_drop.unknown_artist',
                                                    )
                                                }}
                                            </span>
                                        </span>
                                        <span
                                            class="text-muted shrink-0 tabular-nums"
                                        >
                                            {{
                                                transChoice(
                                                    'dj.annual_drop.plays',
                                                    track.plays,
                                                )
                                            }}
                                        </span>
                                    </div>
                                    <span
                                        class="bg-elevated h-1.5 overflow-hidden rounded-full"
                                    >
                                        <span
                                            class="bg-primary block h-full rounded-full"
                                            :style="{
                                                width: share(
                                                    drop.top_tracks,
                                                    track,
                                                ),
                                            }"
                                        />
                                    </span>
                                </li>
                            </ol>
                        </UPageCard>
                    </div>
                </template>
            </div>
        </template>
    </UDashboardPanel>
</template>

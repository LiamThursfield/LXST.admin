<script setup lang="ts">
import { Head, usePoll } from '@inertiajs/vue3';
import {
    VisAxis,
    VisCrosshair,
    VisLine,
    VisTooltip,
    VisXYContainer,
} from '@unovis/vue';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigController';
import { formatDateTime, formatTime } from '@/lib/timezone';

type BpmPoint = {
    played_at: string;
    bpm: number;
    title: string;
    artist: string | null;
};

const props = defineProps<{
    gig: {
        id: number;
        name: string;
        timezone: string;
        starts_at: string;
        ends_at: string;
        is_live: boolean;
        edit_url: string | null;
    };
    stats: {
        plays: {
            count: number;
            songs: number;
            started_at: string | null;
            ended_at: string | null;
            minutes: number | null;
        };
        bpm: {
            series: BpmPoint[];
            average: number | null;
            min: number | null;
            max: number | null;
        };
        keys: Array<{ camelot: string; label: string; count: number }>;
        requests: {
            received: number;
            votes: number;
            statuses: Record<
                'pending' | 'accepted' | 'declined' | 'played',
                number
            >;
            do_not_play: number;
        };
        most_requested: Array<{
            title: string;
            artist: string | null;
            votes: number;
            status: string | null;
            in_library: boolean;
        }>;
        to_buy: Array<{
            title: string;
            artist: string | null;
            count: number;
            votes: number;
            played: boolean;
        }>;
    };
}>();

const { trans, transChoice } = useTrans();
const numberFormat = new Intl.NumberFormat();

// A live dashboard while the gig's on; still afterwards
const { start, stop } = usePoll(
    30000,
    { only: ['stats', 'gig'] },
    { autoStart: false },
);

watch(
    () => props.gig.is_live,
    (live) => (live ? start() : stop()),
    { immediate: true },
);

const setLength = computed(() => {
    const minutes = props.stats.plays.minutes;

    if (minutes === null) {
        return '–';
    }

    return minutes >= 60
        ? trans('dj.stats.tiles.length_value', {
              hours: Math.floor(minutes / 60),
              minutes: minutes % 60,
          })
        : trans('dj.stats.tiles.length_minutes', { minutes });
});

// BPM over time: one series, so no legend; the title names it
const x = (point: BpmPoint) => new Date(point.played_at).getTime();
const y = (point: BpmPoint) => point.bpm;

const escape = (value: string) =>
    value.replace(/[&<>"']/g, (character) => `&#${character.charCodeAt(0)};`);

function tooltip(point: BpmPoint): string {
    return (
        `<div class="text-sm"><div class="font-medium">${escape(point.title)}</div>` +
        (point.artist
            ? `<div class="text-muted">${escape(point.artist)}</div>`
            : '') +
        `<div>${point.bpm} BPM · ${formatTime(point.played_at, props.gig.timezone)}</div></div>`
    );
}

// Recessive grid and axes, so the line carries the chart
const chartTheme = {
    '--vis-axis-grid-color': 'var(--ui-border)',
    '--vis-axis-tick-color': 'var(--ui-border)',
    '--vis-axis-domain-color': 'var(--ui-border)',
    '--vis-axis-tick-label-color': 'var(--ui-text-muted)',
};

// Keys: a ranked bar list in one hue, scaled to the most played key
const maxKeyCount = computed(() =>
    Math.max(1, ...props.stats.keys.map((key) => key.count)),
);

const statusColors = {
    pending: 'neutral',
    accepted: 'primary',
    played: 'success',
    declined: 'error',
} as const;
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar
                :title="`${trans('dj.stats.title')}: ${gig.name}`"
            >
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
                <template #right>
                    <UButton
                        v-if="gig.edit_url"
                        as="Link"
                        :href="gig.edit_url"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-calendar-days"
                        :label="trans('dj.stats.gig_page')"
                    />
                    <UButton
                        as="Link"
                        :href="index.url()"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-arrow-left"
                        :label="trans('dj.gigs.title')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="`${trans('dj.stats.title')}: ${gig.name}`" />

            <div class="flex flex-col gap-6">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-muted text-sm">
                        {{ formatDateTime(gig.starts_at, gig.timezone) }} –
                        {{ formatDateTime(gig.ends_at, gig.timezone) }}
                    </p>
                    <UBadge
                        v-if="gig.is_live"
                        color="success"
                        variant="subtle"
                        icon="i-lucide-radio"
                    >
                        {{ trans('dj.stats.live') }}
                    </UBadge>
                </div>
                <p class="text-muted text-sm">
                    {{ trans('dj.stats.description') }}
                </p>

                <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    <UCard>
                        <p class="text-muted text-sm">
                            {{ trans('dj.stats.tiles.tracks') }}
                        </p>
                        <p class="text-highlighted text-2xl font-semibold">
                            {{ numberFormat.format(stats.plays.count) }}
                        </p>
                        <p class="text-dimmed mt-1 text-xs">
                            {{
                                transChoice(
                                    'dj.stats.tiles.tracks_detail',
                                    stats.plays.songs,
                                )
                            }}
                        </p>
                    </UCard>
                    <UCard>
                        <p class="text-muted text-sm">
                            {{ trans('dj.stats.tiles.length') }}
                        </p>
                        <p class="text-highlighted text-2xl font-semibold">
                            {{ setLength }}
                        </p>
                        <p
                            v-if="
                                stats.plays.started_at && stats.plays.ended_at
                            "
                            class="text-dimmed mt-1 text-xs"
                        >
                            {{
                                formatTime(stats.plays.started_at, gig.timezone)
                            }}
                            –
                            {{ formatTime(stats.plays.ended_at, gig.timezone) }}
                        </p>
                    </UCard>
                    <UCard>
                        <p class="text-muted text-sm">
                            {{ trans('dj.stats.tiles.bpm') }}
                        </p>
                        <p class="text-highlighted text-2xl font-semibold">
                            {{ stats.bpm.average ?? '–' }}
                        </p>
                        <p
                            v-if="stats.bpm.min !== null"
                            class="text-dimmed mt-1 text-xs"
                        >
                            {{
                                trans('dj.stats.tiles.bpm_range', {
                                    min: stats.bpm.min,
                                    max: stats.bpm.max ?? '',
                                })
                            }}
                        </p>
                    </UCard>
                    <UCard>
                        <p class="text-muted text-sm">
                            {{ trans('dj.stats.tiles.requests') }}
                        </p>
                        <p class="text-highlighted text-2xl font-semibold">
                            {{ numberFormat.format(stats.requests.received) }}
                        </p>
                        <p class="text-dimmed mt-1 text-xs">
                            {{
                                trans('dj.stats.tiles.requests_detail', {
                                    played: stats.requests.statuses.played,
                                    votes: stats.requests.votes,
                                })
                            }}
                        </p>
                    </UCard>
                </div>

                <UPageCard
                    :title="trans('dj.stats.bpm_title')"
                    variant="subtle"
                >
                    <VisXYContainer
                        v-if="stats.bpm.series.length > 1"
                        :data="stats.bpm.series"
                        :height="260"
                        :padding="{ top: 10 }"
                        :style="chartTheme"
                    >
                        <VisLine
                            :x="x"
                            :y="y"
                            color="var(--ui-primary)"
                            :line-width="2"
                        />
                        <VisAxis
                            type="x"
                            :tick-format="
                                (value: number) =>
                                    formatTime(
                                        new Date(value).toISOString(),
                                        gig.timezone,
                                    )
                            "
                            :num-ticks="6"
                            :grid-line="false"
                        />
                        <VisAxis type="y" :num-ticks="5" />
                        <VisCrosshair
                            color="var(--ui-primary)"
                            :template="tooltip"
                        />
                        <VisTooltip />
                    </VisXYContainer>
                    <p v-else class="text-muted py-8 text-center text-sm">
                        {{
                            stats.plays.count
                                ? trans('dj.stats.bpm_empty')
                                : trans('dj.stats.nothing')
                        }}
                    </p>
                </UPageCard>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <UPageCard
                        :title="trans('dj.stats.keys_title')"
                        variant="subtle"
                    >
                        <ul
                            v-if="stats.keys.length"
                            class="flex flex-col gap-2"
                        >
                            <li
                                v-for="key in stats.keys"
                                :key="key.camelot"
                                class="grid grid-cols-[3.5rem_1fr_4.5rem] items-center gap-3 text-sm"
                                :title="key.label"
                            >
                                <span class="text-highlighted font-medium">{{
                                    key.camelot
                                }}</span>
                                <span
                                    class="bg-elevated h-2 overflow-hidden rounded-full"
                                >
                                    <span
                                        class="bg-primary block h-full rounded-full"
                                        :style="{
                                            width: `${(key.count / maxKeyCount) * 100}%`,
                                        }"
                                    />
                                </span>
                                <span class="text-muted text-end tabular-nums">
                                    {{
                                        transChoice('dj.stats.plays', key.count)
                                    }}
                                </span>
                            </li>
                        </ul>
                        <p v-else class="text-muted text-sm">
                            {{ trans('dj.stats.keys_empty') }}
                        </p>
                    </UPageCard>

                    <UPageCard
                        :title="trans('dj.stats.requests_title')"
                        variant="subtle"
                    >
                        <template v-if="stats.requests.received">
                            <div class="flex flex-wrap gap-2">
                                <UBadge
                                    v-for="(count, status) in stats.requests
                                        .statuses"
                                    :key="status"
                                    :color="statusColors[status]"
                                    variant="subtle"
                                    size="lg"
                                >
                                    {{
                                        trans(`enums.request_status.${status}`)
                                    }}: {{ count }}
                                </UBadge>
                            </div>
                            <p
                                v-if="stats.requests.do_not_play"
                                class="text-muted text-sm"
                            >
                                {{
                                    transChoice(
                                        'dj.stats.do_not_play',
                                        stats.requests.do_not_play,
                                    )
                                }}
                            </p>
                            <h3
                                class="text-highlighted mt-2 text-sm font-medium"
                            >
                                {{ trans('dj.stats.most_requested') }}
                            </h3>
                            <ol
                                class="divide-default flex flex-col divide-y text-sm"
                            >
                                <li
                                    v-for="(
                                        item, position
                                    ) in stats.most_requested"
                                    :key="position"
                                    class="flex items-center justify-between gap-3 py-2"
                                >
                                    <span class="flex min-w-0 flex-col">
                                        <span
                                            class="text-highlighted truncate"
                                            >{{
                                                item.title || item.artist
                                            }}</span
                                        >
                                        <span
                                            class="text-muted truncate text-xs"
                                        >
                                            <template v-if="item.title">{{
                                                item.artist
                                            }}</template>
                                            <template v-if="!item.in_library">
                                                ·
                                                {{
                                                    trans(
                                                        'dj.stats.not_in_library',
                                                    )
                                                }}
                                            </template>
                                        </span>
                                    </span>
                                    <span
                                        class="text-muted shrink-0 tabular-nums"
                                    >
                                        {{
                                            transChoice(
                                                'dj.stats.votes',
                                                item.votes,
                                            )
                                        }}
                                    </span>
                                </li>
                            </ol>
                        </template>
                        <p v-else class="text-muted text-sm">
                            {{ trans('dj.stats.requests_empty') }}
                        </p>
                    </UPageCard>
                </div>

                <UPageCard
                    :title="trans('dj.stats.to_buy_title')"
                    :description="trans('dj.stats.to_buy_help')"
                    variant="subtle"
                >
                    <ul
                        v-if="stats.to_buy.length"
                        class="divide-default flex flex-col divide-y text-sm"
                    >
                        <li
                            v-for="(item, position) in stats.to_buy"
                            :key="position"
                            class="flex items-center justify-between gap-3 py-2"
                        >
                            <span class="flex min-w-0 flex-col">
                                <span class="text-highlighted truncate">{{
                                    item.title || item.artist
                                }}</span>
                                <span
                                    v-if="item.title"
                                    class="text-muted truncate text-xs"
                                    >{{ item.artist }}</span
                                >
                            </span>
                            <span class="flex shrink-0 items-center gap-2">
                                <UBadge
                                    v-if="item.played"
                                    color="success"
                                    variant="subtle"
                                    size="sm"
                                >
                                    {{ trans('dj.stats.played') }}
                                </UBadge>
                                <span class="text-muted tabular-nums">
                                    {{
                                        transChoice(
                                            'dj.stats.asked',
                                            item.count,
                                        )
                                    }}
                                </span>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-muted text-sm">
                        {{ trans('dj.stats.to_buy_empty') }}
                    </p>
                </UPageCard>
            </div>
        </template>
    </UDashboardPanel>
</template>

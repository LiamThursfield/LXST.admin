<script setup lang="ts">
import {
    VisArea,
    VisAxis,
    VisCrosshair,
    VisLine,
    VisTooltip,
    VisXYContainer,
} from '@unovis/vue';
import type { PageViewAnalyticsData } from '@/types';

type Point = PageViewAnalyticsData['series'][number];

const props = defineProps<{
    analytics: PageViewAnalyticsData;
}>();

const { trans } = useTrans();

const isMonthly = computed(() => props.analytics.range === '12m');
const hasViews = computed(() => props.analytics.totals.views > 0);

const numberFormat = new Intl.NumberFormat();

/** A bucket's date as a short label, e.g. "3 Oct" or "Oct 2026" */
function dateLabel(date: string): string {
    // Read as UTC, as the server counts in UTC
    const parsed = new Date(
        isMonthly.value ? `${date}-01T00:00:00Z` : `${date}T00:00:00Z`,
    );

    return parsed.toLocaleDateString(
        undefined,
        isMonthly.value
            ? { month: 'short', year: 'numeric', timeZone: 'UTC' }
            : { day: 'numeric', month: 'short', timeZone: 'UTC' },
    );
}

const x = (_: Point, index: number) => index;
const views = (point: Point) => point.views;
const visitors = (point: Point) => point.visitors;

function xTickFormat(index: number): string {
    const point = props.analytics.series[index];

    return point ? dateLabel(point.date) : '';
}

function tooltip(point: Point): string {
    return (
        `<div class="text-sm"><div class="font-medium">${dateLabel(point.date)}</div>` +
        `<div>${trans('cms.analytics.views')}: ${numberFormat.format(point.views)}</div>` +
        `<div>${trans('cms.analytics.visitors')}: ${numberFormat.format(point.visitors)}</div></div>`
    );
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <UCard>
                <p class="text-muted text-sm">
                    {{ trans('cms.analytics.views') }}
                </p>
                <p class="text-highlighted text-2xl font-semibold">
                    {{ numberFormat.format(analytics.totals.views) }}
                </p>
            </UCard>
            <UCard>
                <p class="text-muted text-sm">
                    {{ trans('cms.analytics.visitors') }}
                </p>
                <p class="text-highlighted text-2xl font-semibold">
                    {{ numberFormat.format(analytics.totals.visitors) }}
                </p>
                <p class="text-dimmed mt-1 text-xs">
                    {{ trans('cms.analytics.visitors_help') }}
                </p>
            </UCard>
        </div>

        <UCard>
            <VisXYContainer
                v-if="hasViews"
                :data="analytics.series"
                :height="260"
                :padding="{ top: 10 }"
            >
                <VisArea
                    :x="x"
                    :y="views"
                    color="var(--ui-primary)"
                    :opacity="0.1"
                />
                <VisLine :x="x" :y="views" color="var(--ui-primary)" />
                <VisLine
                    :x="x"
                    :y="visitors"
                    color="var(--ui-text-muted)"
                    :line-dash-array="[4, 4]"
                />
                <VisAxis
                    type="x"
                    :x="x"
                    :tick-format="xTickFormat"
                    :num-ticks="6"
                    :grid-line="false"
                />
                <VisAxis
                    type="y"
                    :tick-format="(value: number) => numberFormat.format(value)"
                />
                <VisCrosshair color="var(--ui-primary)" :template="tooltip" />
                <VisTooltip />
            </VisXYContainer>
            <p v-else class="text-muted py-12 text-center text-sm">
                {{ trans('cms.analytics.no_views') }}
            </p>
        </UCard>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <UCard :ui="{ body: 'p-0 sm:p-0' }">
                <template #header>
                    <h3 class="font-semibold">
                        {{ trans('cms.analytics.top_pages') }}
                    </h3>
                </template>
                <ul
                    v-if="analytics.top_pages.length"
                    class="divide-default divide-y"
                >
                    <li
                        v-for="page in analytics.top_pages"
                        :key="page.url"
                        class="flex items-center justify-between gap-4 px-4 py-2 text-sm"
                    >
                        <div class="min-w-0">
                            <p class="text-highlighted truncate">
                                {{
                                    page.name ?? trans('cms.analytics.no_page')
                                }}
                            </p>
                            <p class="text-muted truncate font-mono text-xs">
                                {{ page.url }}
                            </p>
                        </div>
                        <span class="tabular-nums">{{
                            numberFormat.format(page.views)
                        }}</span>
                    </li>
                </ul>
                <p v-else class="text-muted p-4 text-sm">
                    {{ trans('cms.analytics.no_views') }}
                </p>
            </UCard>

            <UCard :ui="{ body: 'p-0 sm:p-0' }">
                <template #header>
                    <h3 class="font-semibold">
                        {{ trans('cms.analytics.top_referrers') }}
                    </h3>
                </template>
                <ul
                    v-if="analytics.top_referrers.length"
                    class="divide-default divide-y"
                >
                    <li
                        v-for="referrer in analytics.top_referrers"
                        :key="referrer.host"
                        class="flex items-center justify-between gap-4 px-4 py-2 text-sm"
                    >
                        <span class="truncate font-mono">{{
                            referrer.host
                        }}</span>
                        <span class="tabular-nums">{{
                            numberFormat.format(referrer.views)
                        }}</span>
                    </li>
                </ul>
                <p v-else class="text-muted p-4 text-sm">
                    {{ trans('cms.analytics.no_referrers') }}
                </p>
            </UCard>
        </div>
    </div>
</template>

<script setup lang="ts">
import MockWindow from './MockWindow.vue';

const { trans } = useTrans();

const nav = computed(() => [
    {
        label: trans('marketing.mock.pages'),
        icon: 'i-lucide-file-text',
        active: true,
    },
    { label: trans('marketing.mock.menus'), icon: 'i-lucide-menu' },
    {
        label: trans('marketing.mock.redirects'),
        icon: 'i-lucide-corner-down-right',
    },
    { label: trans('marketing.mock.analytics'), icon: 'i-lucide-chart-column' },
]);

/** Sample data: a café's pages */
const pages = [
    { title: 'Home', url: '/', status: 'published', views: 4182 },
    { title: 'Menus', url: '/menus', status: 'published', views: 2240 },
    {
        title: 'Private hire',
        url: '/private-hire',
        status: 'published',
        views: 611,
    },
    {
        title: 'Christmas parties',
        url: '/christmas',
        status: 'scheduled',
        views: 0,
    },
    { title: 'Careers', url: '/careers', status: 'draft', views: 0 },
] as const;

const statusColours = {
    published: 'success',
    scheduled: 'info',
    draft: 'neutral',
} as const;

/** Visits per day for the last 30 days */
const visits = [
    32, 41, 38, 45, 60, 72, 55, 40, 44, 47, 51, 66, 81, 70, 49, 52, 58, 54, 63,
    85, 92, 74, 57, 61, 64, 70, 88, 97, 83, 69,
];
const busiestDay = Math.max(...visits);
</script>

<template>
    <MockWindow
        business="The Copper Pan"
        domain="thecopperpan.co.uk"
        :title="trans('marketing.mock.pages')"
        :nav="nav"
    >
        <div class="border-default rounded-lg border px-3 pb-3 pt-2.5">
            <p class="text-muted text-xs">
                {{ trans('marketing.mock.views') }}
            </p>
            <div class="mt-2 flex h-16 items-end gap-[3px]">
                <span
                    v-for="(count, day) in visits"
                    :key="day"
                    class="bg-primary/70 flex-1 rounded-[2px]"
                    :style="{ height: `${(count / busiestDay) * 100}%` }"
                />
            </div>
        </div>

        <ul
            class="divide-default border-default mt-4 divide-y rounded-lg border"
        >
            <li
                v-for="page in pages"
                :key="page.url"
                class="flex items-center gap-3 px-3 py-2.5 text-sm"
            >
                <span class="min-w-0 flex-1">
                    <span class="text-highlighted block truncate font-medium">
                        {{ page.title }}
                    </span>
                    <span class="text-muted block truncate text-xs">
                        {{ page.url }}
                    </span>
                </span>
                <span
                    v-if="page.views"
                    class="text-muted hidden text-xs tabular-nums sm:inline"
                >
                    {{ page.views.toLocaleString('en-GB') }}
                </span>
                <UBadge
                    :color="statusColours[page.status]"
                    variant="subtle"
                    size="sm"
                    :label="trans(`marketing.mock.${page.status}`)"
                />
            </li>
        </ul>
    </MockWindow>
</template>

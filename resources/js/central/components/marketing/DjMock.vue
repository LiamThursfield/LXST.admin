<script setup lang="ts">
import MockWindow from './MockWindow.vue';

const { trans } = useTrans();

const nav = computed(() => [
    {
        label: trans('marketing.mock.gigs'),
        icon: 'i-lucide-party-popper',
        active: true,
    },
    { label: trans('marketing.mock.library'), icon: 'i-lucide-library' },
    { label: trans('marketing.mock.setlists'), icon: 'i-lucide-list-music' },
]);

/** Sample data: song requests at a wedding */
const requests = [
    {
        title: 'September',
        artist: 'Earth, Wind & Fire',
        votes: 14,
        status: 'played',
    },
    {
        title: 'Mr. Brightside',
        artist: 'The Killers',
        votes: 11,
        status: 'in_library',
    },
    {
        title: 'Dancing in the Moonlight',
        artist: 'Toploader',
        votes: 7,
        status: 'in_library',
    },
    {
        title: 'Valerie',
        artist: 'Mark Ronson, Amy Winehouse',
        votes: 5,
        status: 'in_library',
    },
    {
        title: 'Our first dance (live version)',
        artist: 'Unknown',
        votes: 1,
        status: 'not_found',
    },
] as const;

const statusColours = {
    played: 'success',
    in_library: 'neutral',
    not_found: 'warning',
} as const;
</script>

<template>
    <MockWindow
        business="Static Lanes DJ"
        domain="staticlanes.co.uk"
        title="Hughes wedding · The Old Granary"
        :nav="nav"
    >
        <template #actions>
            <UBadge
                color="success"
                variant="subtle"
                size="sm"
                icon="i-lucide-radio"
                :label="trans('marketing.mock.open')"
            />
        </template>

        <div
            class="bg-elevated flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm"
        >
            <span
                class="bg-primary text-inverted flex size-8 shrink-0 items-center justify-center rounded-md"
            >
                <UIcon name="i-lucide-audio-lines" class="size-4" />
            </span>
            <span class="min-w-0 flex-1">
                <span class="text-muted block text-xs">
                    {{ trans('marketing.mock.now_playing') }}
                </span>
                <span class="text-highlighted block truncate font-medium">
                    September · Earth, Wind & Fire
                </span>
            </span>
            <span class="text-muted shrink-0 text-xs tabular-nums">
                126 BPM · 10A
            </span>
        </div>

        <p class="text-muted mb-2 mt-4 text-xs font-medium">
            {{ trans('marketing.mock.requests') }}
        </p>
        <ul class="divide-default border-default divide-y rounded-lg border">
            <li
                v-for="request in requests"
                :key="request.title"
                class="flex items-center gap-3 px-3 py-2.5 text-sm"
            >
                <span
                    class="text-muted flex w-8 shrink-0 items-center gap-0.5 text-xs tabular-nums"
                >
                    <UIcon name="i-lucide-chevron-up" class="size-3.5" />
                    {{ request.votes }}
                </span>
                <span class="min-w-0 flex-1">
                    <span class="text-highlighted block truncate font-medium">
                        {{ request.title }}
                    </span>
                    <span class="text-muted block truncate text-xs">
                        {{ request.artist }}
                    </span>
                </span>
                <UBadge
                    :color="statusColours[request.status]"
                    variant="subtle"
                    size="sm"
                    class="shrink-0"
                    :label="trans(`marketing.mock.${request.status}`)"
                />
            </li>
        </ul>
    </MockWindow>
</template>

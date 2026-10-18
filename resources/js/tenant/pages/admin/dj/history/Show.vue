<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/HistoryController';
import { formatDateTime } from '@/lib/timezone';
import type { HistorySessionData, PlayedTrackData } from '@/types';

const props = defineProps<{
    session: { data: HistorySessionData };
    plays: { data: PlayedTrackData[] };
    timezone: string;
}>();

const { trans, transChoice } = useTrans();
const session = computed(() => props.session.data);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="session.name">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
                <template #right>
                    <UButton
                        :as="Link"
                        :href="index.url()"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-arrow-left"
                        :label="trans('dj.history.show.back')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="session.name" />
            <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <div class="flex flex-wrap items-center gap-2">
                    <UBadge v-if="session.gig" color="primary" variant="subtle">
                        <Link :href="session.gig.url">{{
                            session.gig.name
                        }}</Link>
                    </UBadge>
                    <UBadge v-else color="neutral" variant="subtle">{{
                        trans('dj.history.not_linked')
                    }}</UBadge>
                    <UBadge
                        :color="
                            session.include_in_stats ? 'success' : 'neutral'
                        "
                        variant="subtle"
                    >
                        {{
                            session.include_in_stats
                                ? trans('dj.history.in_stats')
                                : trans('dj.history.not_in_stats')
                        }}
                    </UBadge>
                    <span v-if="session.library" class="text-muted text-sm">
                        {{
                            trans('dj.history.show.from_library', {
                                library: session.library,
                            })
                        }}
                    </span>
                </div>

                <UPageCard
                    :title="
                        transChoice('dj.history.plays', session.plays_count)
                    "
                    variant="subtle"
                >
                    <ol class="divide-default flex flex-col divide-y text-sm">
                        <li
                            v-for="play in plays.data"
                            :key="play.id"
                            class="flex justify-between gap-3 py-2"
                        >
                            <span class="flex min-w-0 flex-col">
                                <Link
                                    v-if="play.track_url ?? play.song_url"
                                    :href="
                                        (play.track_url ??
                                            play.song_url) as string
                                    "
                                    class="text-highlighted font-medium hover:underline"
                                >
                                    {{ play.title }}
                                </Link>
                                <span
                                    v-else
                                    class="text-highlighted font-medium"
                                    >{{ play.title }}</span
                                >
                                <span class="text-muted text-xs">{{
                                    play.artist
                                }}</span>
                            </span>
                            <span
                                v-if="play.played_at"
                                class="text-muted shrink-0 text-xs"
                                >{{
                                    formatDateTime(play.played_at, timezone)
                                }}</span
                            >
                        </li>
                    </ol>
                </UPageCard>
            </div>
        </template>
    </UDashboardPanel>
</template>

<script setup lang="ts">
import { show as gigTracklist } from '@/actions/App/Http/Controllers/Tenant/Api/V1/Dj/GigTracklistController';
import { publicApi } from '@/lib/publicApi';
import { formatTime } from '@/lib/timezone';
import type { PublicTrackData } from '@/types';

const props = defineProps<{
    /** The gig's public ID */
    gigId: string;
    timezone: string;
}>();

const { trans } = useTrans();

const nowPlaying = ref<PublicTrackData | null>(null);
const tracks = ref<PublicTrackData[]>([]);
const page = ref(1);
const lastPage = ref(1);
const loaded = ref(false);

async function load() {
    try {
        const response = await publicApi<{
            data: PublicTrackData[];
            now_playing: PublicTrackData | null;
            meta: { current_page: number; last_page: number };
        }>(gigTracklist(props.gigId, { query: { page: page.value } }));

        nowPlaying.value = response.now_playing;
        tracks.value = response.data;
        lastPage.value = response.meta.last_page;
    } catch {
        // Kept as it was; the next refresh tries again
    } finally {
        loaded.value = true;
    }
}

/** The tracks before this one: the one playing now is shown above, so it isn't listed twice */
const recent = computed(() =>
    tracks.value.filter((track) => track.id !== nowPlaying.value?.id),
);

function goTo(next: number) {
    page.value = next;
    load();
}

// The newest page keeps up with the set; older pages stay put while they're being read
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    load();
    timer = setInterval(() => page.value === 1 && load(), 15000);
});
onUnmounted(() => clearInterval(timer));
</script>

<template>
    <div class="flex flex-col gap-6">
        <UPageCard
            :title="trans('dj.hosted.tracklist.now_playing')"
            variant="subtle"
        >
            <div v-if="nowPlaying" class="flex items-center gap-3">
                <UIcon
                    name="i-lucide-disc-3"
                    class="text-primary size-8 shrink-0 animate-spin [animation-duration:3s]"
                />
                <div class="flex min-w-0 flex-col">
                    <span
                        class="text-highlighted truncate text-lg font-semibold"
                        >{{ nowPlaying.title }}</span
                    >
                    <span class="text-muted truncate">{{
                        nowPlaying.artist
                    }}</span>
                </div>
            </div>
            <p v-else-if="loaded" class="text-muted text-sm">
                {{ trans('dj.hosted.tracklist.nothing_playing') }}
            </p>
            <USkeleton v-else class="h-12 w-full" />
        </UPageCard>

        <UPageCard
            :title="trans('dj.hosted.tracklist.recent')"
            variant="subtle"
        >
            <ol
                v-if="recent.length"
                class="divide-default flex flex-col divide-y"
            >
                <li
                    v-for="track in recent"
                    :key="track.id"
                    class="flex items-center justify-between gap-3 py-2"
                >
                    <span class="flex min-w-0 flex-col">
                        <span class="text-highlighted truncate">{{
                            track.title
                        }}</span>
                        <span class="text-muted truncate text-sm">{{
                            track.artist
                        }}</span>
                    </span>
                    <span
                        v-if="track.played_at"
                        class="text-muted shrink-0 text-xs"
                    >
                        {{ formatTime(track.played_at, timezone) }}
                    </span>
                </li>
            </ol>
            <p v-else-if="loaded" class="text-muted text-sm">
                {{ trans('dj.hosted.tracklist.empty') }}
            </p>
            <USkeleton v-else class="h-24 w-full" />

            <div v-if="lastPage > 1" class="flex justify-between gap-2">
                <UButton
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-chevron-left"
                    :label="trans('dj.hosted.tracklist.newer')"
                    :disabled="page <= 1"
                    @click="goTo(page - 1)"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    trailing-icon="i-lucide-chevron-right"
                    :label="trans('dj.hosted.tracklist.older')"
                    :disabled="page >= lastPage"
                    @click="goTo(page + 1)"
                />
            </div>
        </UPageCard>
    </div>
</template>

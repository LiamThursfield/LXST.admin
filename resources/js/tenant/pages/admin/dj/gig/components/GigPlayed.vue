<script setup lang="ts">
import { Link, useForm, useHttp } from '@inertiajs/vue3';
// Imported explicitly, as the generated component types don't include UTooltip
import UTooltip from '@nuxt/ui/components/Tooltip.vue';
import { search as searchSongs } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalTrackController';
import {
    destroy as destroyPlay,
    store as storePlay,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigPlayController';
import { unlink } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/HistoryBulkController';
import { formatDateTime } from '@/lib/timezone';
import type { GigData, HistorySessionData, PlayedTrackData } from '@/types';

const props = defineProps<{
    gig: GigData;
    played: PlayedTrackData[];
    sessions: HistorySessionData[];
}>();

const { trans, transChoice } = useTrans();

function unlinkSession(session: HistorySessionData) {
    router.post(
        unlink.url(),
        { session_ids: [session.id] },
        { preserveScroll: true },
    );
}

function removePlay(play: PlayedTrackData) {
    router.delete(destroyPlay.url({ gig: props.gig.id, play: play.id }), {
        preserveScroll: true,
    });
}

// Adding a played song by hand, at a time on the gig's clock
const songSearch = ref('');
const songResults = ref<
    Array<{ id: number; title: string; artist: string | null }>
>([]);
const searcher = useHttp();
const addForm = useForm({
    canonical_track_id: null as number | null,
    played_at: props.gig.starts_at_local ?? '',
});

const findSongs = useDebounceFn(async () => {
    if (songSearch.value.trim() === '') {
        songResults.value = [];

        return;
    }

    try {
        const response = (await searcher.get(
            searchSongs.url({ query: { search: songSearch.value } }),
        )) as {
            data: Array<{ id: number; title: string; artist: string | null }>;
        };
        songResults.value = response.data;
    } catch {
        songResults.value = [];
    }
}, 250);

watch(songSearch, () => findSongs());

function add() {
    addForm.submit(storePlay(props.gig.id), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset('canonical_track_id');
            songSearch.value = '';
        },
    });
}
</script>

<template>
    <UPageCard
        :title="trans('dj.history.played.title')"
        :description="trans('dj.history.played.help')"
        variant="subtle"
    >
        <ol
            v-if="played.length"
            class="divide-default flex max-h-96 flex-col divide-y overflow-y-auto pe-1 text-sm"
        >
            <li
                v-for="play in played"
                :key="play.id"
                class="flex items-center justify-between gap-3 py-2"
            >
                <span class="flex min-w-0 flex-col">
                    <Link
                        v-if="play.song_url"
                        :href="play.song_url"
                        class="text-highlighted font-medium hover:underline"
                        >{{ play.title }}</Link
                    >
                    <span v-else class="text-highlighted font-medium">{{
                        play.title
                    }}</span>
                    <span class="text-muted text-xs">
                        {{ play.artist }}
                        <template v-if="play.is_manual">
                            · {{ trans('dj.history.played.added_by_hand') }}
                        </template>
                    </span>
                </span>
                <span class="flex shrink-0 items-center gap-2">
                    <span v-if="play.played_at" class="text-muted text-xs">{{
                        formatDateTime(play.played_at, gig.timezone)
                    }}</span>
                    <UButton
                        v-if="play.is_manual"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-x"
                        :aria-label="trans('dj.history.played.remove')"
                        @click="removePlay(play)"
                    />
                </span>
            </li>
        </ol>
        <p v-else class="text-muted text-sm">
            {{ trans('dj.history.played.empty') }}
        </p>

        <template v-if="sessions.length">
            <USeparator class="my-2" />
            <h3 class="text-highlighted text-sm font-medium">
                {{ trans('dj.history.played.sessions') }}
            </h3>
            <ul class="flex flex-col gap-1 text-sm">
                <li
                    v-for="session in sessions"
                    :key="session.id"
                    class="flex items-center justify-between gap-2"
                >
                    <span class="min-w-0">
                        <Link :href="session.url" class="hover:underline">{{
                            session.name
                        }}</Link>
                        <span class="text-muted">
                            ·
                            {{
                                transChoice(
                                    'dj.history.plays',
                                    session.plays_count,
                                )
                            }}
                        </span>
                        <UTooltip
                            v-if="session.is_superseded"
                            :text="trans('dj.history.superseded_help')"
                        >
                            <UBadge
                                color="neutral"
                                variant="subtle"
                                size="sm"
                                class="ms-1"
                            >
                                {{ trans('dj.history.superseded') }}
                            </UBadge>
                        </UTooltip>
                    </span>
                    <UButton
                        v-if="session.source !== 'manual'"
                        size="xs"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-unlink"
                        :label="trans('dj.history.bulk.unlink')"
                        @click="unlinkSession(session)"
                    />
                </li>
            </ul>
        </template>

        <USeparator class="my-2" />

        <div class="flex flex-col gap-3">
            <div class="flex flex-col">
                <h3 class="text-highlighted text-sm font-medium">
                    {{ trans('dj.history.played.add') }}
                </h3>
                <span class="text-muted text-xs">{{
                    trans('dj.history.played.add_help')
                }}</span>
            </div>
            <UInput
                v-model="songSearch"
                icon="i-lucide-search"
                class="w-full"
                :loading="searcher.processing"
                :placeholder="trans('dj.setlists.search_songs')"
            />
            <ul
                v-if="songSearch"
                class="flex max-h-56 flex-col gap-1 overflow-y-auto"
            >
                <li v-for="song in songResults" :key="song.id">
                    <button
                        type="button"
                        class="hover:bg-elevated flex w-full flex-col rounded-md px-3 py-2 text-start"
                        :class="{
                            'bg-elevated ring-primary ring-1 ring-inset':
                                addForm.canonical_track_id === song.id,
                        }"
                        @click="addForm.canonical_track_id = song.id"
                    >
                        <span class="text-highlighted text-sm font-medium">{{
                            song.title
                        }}</span>
                        <span v-if="song.artist" class="text-muted text-xs">{{
                            song.artist
                        }}</span>
                    </button>
                </li>
                <li
                    v-if="!songResults.length && !searcher.processing"
                    class="text-muted px-3 py-2 text-sm"
                >
                    {{ trans('dj.setlists.no_results') }}
                </li>
            </ul>
            <p
                v-if="addForm.errors.canonical_track_id"
                class="text-error text-sm"
            >
                {{ addForm.errors.canonical_track_id }}
            </p>
            <div class="flex flex-wrap items-end gap-2">
                <UFormField
                    :label="trans('dj.history.fields.played_at')"
                    :error="addForm.errors.played_at"
                    :hint="gig.timezone"
                >
                    <UInput v-model="addForm.played_at" type="datetime-local" />
                </UFormField>
                <UButton
                    icon="i-lucide-plus"
                    :label="trans('dj.setlists.add')"
                    :loading="addForm.processing"
                    :disabled="!addForm.canonical_track_id"
                    @click="add"
                />
            </div>
        </div>
    </UPageCard>
</template>

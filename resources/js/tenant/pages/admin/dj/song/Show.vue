<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { search as searchArtists } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalArtistController';
import { verify } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalTrackController';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SongController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import CanonicalMergeModal from '@/tenant/components/dj/CanonicalMergeModal.vue';
import type {
    CanonicalMergeData,
    LibraryTrackData,
    MusicalKeyData,
    SelectItem,
} from '@/types';

const props = defineProps<{
    song: {
        id: number;
        title: string;
        artist: { id: number; name: string; url: string } | null;
        bpm: number | null;
        duration: string | null;
        key: MusicalKeyData | null;
        is_verified: boolean;
    };
    libraryTracks: { data: LibraryTrackData[] };
    playsCount: number;
    plays: Array<{
        session: string;
        library: string | null;
        played_at: string | null;
    }>;
    merges: { data: CanonicalMergeData[] };
    setlists: Array<{
        id: number;
        name: string;
        gig: string | null;
        type: string | null;
        url: string;
    }>;
    keys: SelectItem[];
    canManage: boolean;
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const form = useForm({
    title: props.song.title,
    canonical_artist_id: props.song.artist?.id ?? null,
    bpm: props.song.bpm === null ? '' : String(props.song.bpm),
    key: props.song.key?.value ?? '',
    duration: props.song.duration ?? '',
});

function save() {
    form.transform((data) => ({
        ...data,
        bpm: data.bpm === '' ? null : data.bpm,
        key: data.key === '' ? null : data.key,
        duration: data.duration === '' ? null : data.duration,
    })).submit(update(props.song.id), { preserveScroll: true });
}

// The artist is picked by searching, as there can be thousands
const artistSearch = ref('');
const artistItems = ref<Array<{ label: string; value: number | null }>>([
    { label: trans('dj.songs.fields.no_artist'), value: null },
    ...(props.song.artist
        ? [{ label: props.song.artist.name, value: props.song.artist.id }]
        : []),
]);
const artistSearcher = useHttp();

const findArtists = useDebounceFn(async () => {
    try {
        const response = (await artistSearcher.get(
            searchArtists.url({ query: { search: artistSearch.value } }),
        )) as { data: Array<{ id: number; name: string }> };

        const current = artistItems.value.find(
            (item) => item.value === form.canonical_artist_id,
        );
        artistItems.value = [
            { label: trans('dj.songs.fields.no_artist'), value: null },
            ...(current && current.value !== null ? [current] : []),
            ...response.data
                .filter((artist) => artist.id !== current?.value)
                .map((artist) => ({ label: artist.name, value: artist.id })),
        ];
    } catch {
        // Keep the current choices
    }
}, 250);

watch(artistSearch, () => findArtists());

const verifying = ref(false);

function setVerified(verified: boolean) {
    router.patch(
        verify.url(props.song.id),
        { verified },
        {
            preserveScroll: true,
            onStart: () => (verifying.value = true),
            onFinish: () => (verifying.value = false),
        },
    );
}

const mergeOpen = ref(false);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="song.title">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        as="Link"
                        :href="index.url()"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-arrow-left"
                        :label="trans('dj.songs.show.back')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="song.title" />

            <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h1
                            class="text-highlighted flex items-center gap-2 text-2xl font-semibold"
                        >
                            {{ song.title }}
                            <UBadge
                                v-if="song.is_verified"
                                color="success"
                                variant="subtle"
                            >
                                {{ trans('dj.matching.verified') }}
                            </UBadge>
                        </h1>
                        <Link
                            v-if="song.artist"
                            :href="song.artist.url"
                            class="text-muted hover:underline"
                        >
                            {{ song.artist.name }}
                        </Link>
                        <span v-else class="text-muted">
                            {{ trans('dj.tracks.unknown_artist') }}
                        </span>
                        <span class="text-muted text-sm">
                            {{
                                [song.bpm, song.key?.label, song.duration]
                                    .filter(Boolean)
                                    .join(' · ')
                            }}
                        </span>
                    </div>

                    <div v-if="canManage" class="flex flex-wrap gap-2">
                        <UButton
                            size="sm"
                            color="neutral"
                            variant="outline"
                            :icon="
                                song.is_verified
                                    ? 'i-lucide-shield-off'
                                    : 'i-lucide-shield-check'
                            "
                            :label="
                                song.is_verified
                                    ? trans('dj.matching.track.unverify')
                                    : trans('dj.matching.track.verify')
                            "
                            :loading="verifying"
                            @click="setVerified(!song.is_verified)"
                        />
                        <UButton
                            size="sm"
                            color="neutral"
                            variant="outline"
                            icon="i-lucide-merge"
                            :label="trans('dj.matching.track.merge')"
                            @click="mergeOpen = true"
                        />
                    </div>
                </div>

                <UPageCard
                    v-if="canManage"
                    :title="trans('dj.songs.show.edit')"
                    :description="trans('dj.songs.show.edit_help')"
                    variant="subtle"
                >
                    <form
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                        @submit.prevent="save"
                    >
                        <UFormField
                            :label="trans('dj.songs.fields.title')"
                            :error="form.errors.title"
                            class="sm:col-span-2"
                            required
                        >
                            <UInput v-model="form.title" class="w-full" />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.songs.fields.artist')"
                            :error="form.errors.canonical_artist_id"
                            class="sm:col-span-2"
                        >
                            <USelectMenu
                                v-model="form.canonical_artist_id"
                                v-model:search-term="artistSearch"
                                :items="artistItems"
                                value-key="value"
                                ignore-filter
                                :loading="artistSearcher.processing"
                                :search-input="{
                                    placeholder: trans(
                                        'dj.songs.fields.artist_search',
                                    ),
                                }"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.songs.fields.bpm')"
                            :error="form.errors.bpm"
                        >
                            <UInput
                                v-model="form.bpm"
                                type="number"
                                step="0.01"
                                min="1"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.songs.fields.key')"
                            :error="form.errors.key"
                        >
                            <USelect
                                v-model="form.key"
                                :items="keys"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.songs.fields.duration')"
                            :help="trans('dj.songs.fields.duration_help')"
                            :error="form.errors.duration"
                        >
                            <UInput
                                v-model="form.duration"
                                placeholder="3:45"
                                class="w-full"
                            />
                        </UFormField>
                        <div class="flex items-end justify-end">
                            <UButton
                                type="submit"
                                :loading="form.processing"
                                :label="trans('dj.songs.show.save')"
                            />
                        </div>
                    </form>
                </UPageCard>

                <UPageCard
                    :title="trans('dj.songs.show.library_tracks')"
                    variant="subtle"
                >
                    <ul class="divide-default flex flex-col divide-y text-sm">
                        <li
                            v-for="track in libraryTracks.data"
                            :key="track.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <div class="flex min-w-0 flex-col">
                                <Link
                                    :href="track.url"
                                    class="text-highlighted font-medium hover:underline"
                                >
                                    {{ track.title }}
                                </Link>
                                <span class="text-muted text-xs">
                                    {{
                                        track.artist?.name ??
                                        trans('dj.tracks.unknown_artist')
                                    }}
                                    · {{ track.library }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <UBadge
                                    v-if="track.is_missing"
                                    color="warning"
                                    variant="subtle"
                                    size="sm"
                                >
                                    {{ trans('dj.tracks.missing') }}
                                </UBadge>
                                <span class="text-muted text-xs">
                                    {{
                                        [
                                            track.bpm,
                                            track.key?.camelot,
                                            track.duration,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')
                                    }}
                                </span>
                            </div>
                        </li>
                    </ul>
                </UPageCard>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <UPageCard
                        :title="trans('dj.songs.show.history')"
                        variant="subtle"
                    >
                        <template v-if="playsCount">
                            <p class="text-sm">
                                {{
                                    trans('dj.songs.show.history_count', {
                                        count: playsCount,
                                    })
                                }}
                            </p>
                            <ul class="flex flex-col gap-1 text-sm">
                                <li
                                    v-for="(play, playIndex) in plays"
                                    :key="playIndex"
                                    class="flex justify-between gap-2"
                                >
                                    <span>
                                        {{ play.session }}
                                        <span
                                            v-if="play.library"
                                            class="text-muted"
                                            >· {{ play.library }}</span
                                        >
                                    </span>
                                    <span
                                        v-if="play.played_at"
                                        class="text-muted"
                                    >
                                        {{
                                            formatDateTime(
                                                play.played_at,
                                                timezone,
                                            )
                                        }}
                                    </span>
                                </li>
                            </ul>
                        </template>
                        <p v-else class="text-muted text-sm">
                            {{ trans('dj.songs.show.no_history') }}
                        </p>
                    </UPageCard>

                    <UPageCard
                        :title="trans('dj.songs.show.setlists')"
                        variant="subtle"
                    >
                        <ul
                            v-if="setlists.length"
                            class="flex flex-col gap-1 text-sm"
                        >
                            <li
                                v-for="setlist in setlists"
                                :key="setlist.id"
                                class="flex justify-between gap-2"
                            >
                                <Link
                                    :href="setlist.url"
                                    class="hover:underline"
                                >
                                    {{ setlist.name }}
                                    <span v-if="setlist.gig" class="text-muted"
                                        >· {{ setlist.gig }}</span
                                    >
                                </Link>
                                <span
                                    v-if="setlist.type"
                                    class="text-muted text-xs"
                                    >{{ setlist.type }}</span
                                >
                            </li>
                        </ul>
                        <p v-else class="text-muted text-sm">
                            {{ trans('dj.songs.show.no_setlists') }}
                        </p>
                    </UPageCard>

                    <UPageCard
                        :title="trans('dj.songs.show.merges')"
                        variant="subtle"
                    >
                        <ul
                            v-if="merges.data.length"
                            class="flex flex-col gap-2 text-sm"
                        >
                            <li
                                v-for="merge in merges.data"
                                :key="merge.id"
                                class="flex flex-col"
                            >
                                <span>
                                    {{
                                        trans(
                                            `dj.matching.history_entry.${merge.action}`,
                                            {
                                                source: merge.source_name,
                                                target: merge.target_name,
                                                count: merge.moved_count,
                                            },
                                        )
                                    }}
                                </span>
                                <span class="text-muted text-xs">
                                    {{
                                        merge.user ??
                                        trans('dj.matching.automatic')
                                    }}
                                    ·
                                    {{
                                        formatDateTime(
                                            merge.created_at,
                                            timezone,
                                        )
                                    }}
                                </span>
                            </li>
                        </ul>
                        <p v-else class="text-muted text-sm">
                            {{ trans('dj.songs.show.no_merges') }}
                        </p>
                    </UPageCard>
                </div>
            </div>

            <CanonicalMergeModal
                v-model:open="mergeOpen"
                record="track"
                :source-id="song.id"
                :initial-search="song.title"
                :title="trans('dj.matching.track.merge_title')"
                :description="trans('dj.matching.track.merge_help')"
                :placeholder="trans('dj.matching.track.merge_search')"
            />
        </template>
    </UDashboardPanel>
</template>

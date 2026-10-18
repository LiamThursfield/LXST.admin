<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    index,
    update,
    verify,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalArtistController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import CanonicalMergeModal from '@/tenant/components/dj/CanonicalMergeModal.vue';
import type { CanonicalMergeData } from '@/types';

const props = defineProps<{
    artist: {
        id: number;
        name: string;
        is_verified: boolean;
        songs_url: string;
    };
    songs: Array<{
        id: number;
        title: string;
        library_tracks_count: number;
        is_verified: boolean;
        url: string;
    }>;
    songsCount: number;
    libraryArtists: Array<{
        id: number;
        name: string;
        library: string;
        tracks_url: string;
    }>;
    merges: { data: CanonicalMergeData[] };
    canManage: boolean;
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const form = useForm({ name: props.artist.name });

function save() {
    form.submit(update(props.artist.id), { preserveScroll: true });
}

const verifying = ref(false);

function setVerified(verified: boolean) {
    router.patch(
        verify.url(props.artist.id),
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
            <UDashboardNavbar :title="artist.name">
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
                        :label="trans('dj.canonical_artists.show.back')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="artist.name" />

            <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <h1
                        class="text-highlighted flex items-center gap-2 text-2xl font-semibold"
                    >
                        {{ artist.name }}
                        <UBadge
                            v-if="artist.is_verified"
                            color="success"
                            variant="subtle"
                        >
                            {{ trans('dj.matching.verified') }}
                        </UBadge>
                    </h1>

                    <div v-if="canManage" class="flex flex-wrap gap-2">
                        <UButton
                            size="sm"
                            color="neutral"
                            variant="outline"
                            :icon="
                                artist.is_verified
                                    ? 'i-lucide-shield-off'
                                    : 'i-lucide-shield-check'
                            "
                            :label="
                                artist.is_verified
                                    ? trans('dj.matching.track.unverify')
                                    : trans('dj.matching.track.verify')
                            "
                            :loading="verifying"
                            @click="setVerified(!artist.is_verified)"
                        />
                        <UButton
                            size="sm"
                            color="neutral"
                            variant="outline"
                            icon="i-lucide-merge"
                            :label="trans('dj.canonical_artists.show.merge')"
                            @click="mergeOpen = true"
                        />
                    </div>
                </div>

                <UPageCard
                    v-if="canManage"
                    :title="trans('dj.canonical_artists.show.rename')"
                    :description="
                        trans('dj.canonical_artists.show.rename_help')
                    "
                    variant="subtle"
                >
                    <form
                        class="flex flex-col gap-3 sm:flex-row sm:items-start"
                        @submit.prevent="save"
                    >
                        <UFormField :error="form.errors.name" class="flex-1">
                            <UInput
                                v-model="form.name"
                                class="w-full"
                                :aria-label="
                                    trans('dj.canonical_artists.fields.name')
                                "
                            />
                        </UFormField>
                        <UButton
                            type="submit"
                            :loading="form.processing"
                            :label="trans('dj.songs.show.save')"
                        />
                    </form>
                </UPageCard>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <UPageCard
                        :title="trans('dj.canonical_artists.show.songs')"
                        variant="subtle"
                    >
                        <ul class="flex flex-col gap-1 text-sm">
                            <li
                                v-for="song in songs"
                                :key="song.id"
                                class="flex justify-between gap-2"
                            >
                                <Link
                                    :href="song.url"
                                    class="hover:underline"
                                    >{{ song.title }}</Link
                                >
                                <span class="text-muted text-xs">
                                    {{
                                        trans('dj.songs.sources', {
                                            count: song.library_tracks_count,
                                        })
                                    }}
                                </span>
                            </li>
                        </ul>
                        <UButton
                            v-if="songsCount > songs.length"
                            :as="Link"
                            :href="artist.songs_url"
                            variant="link"
                            class="px-0"
                            :label="
                                trans('dj.canonical_artists.show.all_songs', {
                                    count: songsCount,
                                })
                            "
                        />
                    </UPageCard>

                    <div class="flex flex-col gap-6">
                        <UPageCard
                            :title="
                                trans(
                                    'dj.canonical_artists.show.library_artists',
                                )
                            "
                            variant="subtle"
                        >
                            <ul class="flex flex-col gap-1 text-sm">
                                <li
                                    v-for="libraryArtist in libraryArtists"
                                    :key="libraryArtist.id"
                                >
                                    <Link
                                        :href="libraryArtist.tracks_url"
                                        class="hover:underline"
                                    >
                                        {{ libraryArtist.name }}
                                    </Link>
                                    <span class="text-muted">
                                        · {{ libraryArtist.library }}</span
                                    >
                                </li>
                            </ul>
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
            </div>

            <CanonicalMergeModal
                v-model:open="mergeOpen"
                record="artist"
                :source-id="artist.id"
                :initial-search="artist.name"
                :title="trans('dj.canonical_artists.show.merge_title')"
                :description="trans('dj.canonical_artists.show.merge_help')"
                :placeholder="trans('dj.canonical_artists.show.merge_search')"
            />
        </template>
    </UDashboardPanel>
</template>

<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { search as searchArtists } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalArtistController';
import { search as searchTracks } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalTrackController';
import { assign as assignArtists } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryArtistBulkController';
import { assign as assignTracks } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryTrackBulkController';

/**
 * Assigns the selected library tracks to a song, or library artists to an artist: an existing
 * one picked by searching, or a new one
 */
const props = defineProps<{
    record: 'track' | 'artist';
    ids: number[];
    /** What a new record is called unless changed, e.g. the first selected track */
    defaultName: string;
    /** How many artists the selected tracks are by, to warn when it's more than one */
    artistCount?: number;
}>();

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ assigned: [] }>();

const { trans } = useTrans();

type Option = { id: number; label: string; detail: string | null };

const mode = ref<'existing' | 'new'>('existing');
const search = ref('');
const results = ref<Option[]>([]);
const target = ref<Option | null>(null);
const newName = ref('');
const submitting = ref(false);
const errors = ref<Record<string, string>>({});
const searcher = useHttp();

const find = useDebounceFn(async () => {
    try {
        if (props.record === 'artist') {
            const response = (await searcher.get(
                searchArtists.url({ query: { search: search.value } }),
            )) as {
                data: Array<{ id: number; name: string; tracks_count: number }>;
            };
            results.value = response.data.map((artist) => ({
                id: artist.id,
                label: artist.name,
                detail: trans('dj.songs.total', { count: artist.tracks_count }),
            }));
        } else {
            const response = (await searcher.get(
                searchTracks.url({ query: { search: search.value } }),
            )) as {
                data: Array<{
                    id: number;
                    title: string;
                    artist: string | null;
                }>;
            };
            results.value = response.data.map((track) => ({
                id: track.id,
                label: track.title,
                detail: track.artist,
            }));
        }
    } catch {
        results.value = [];
    }
}, 250);

watch(search, () => find());

watch(open, (isOpen) => {
    if (isOpen) {
        mode.value = 'existing';
        search.value = props.defaultName;
        newName.value = props.defaultName;
        target.value = null;
        errors.value = {};
        find();
    }
});

const canSubmit = computed(() =>
    mode.value === 'existing'
        ? target.value !== null
        : newName.value.trim() !== '',
);

function submit() {
    const isArtist = props.record === 'artist';
    const data = isArtist
        ? {
              artist_ids: props.ids,
              canonical_artist_id:
                  mode.value === 'existing' ? target.value?.id : null,
              new_artist_name: mode.value === 'new' ? newName.value : null,
          }
        : {
              track_ids: props.ids,
              song_id: mode.value === 'existing' ? target.value?.id : null,
              new_song_title: mode.value === 'new' ? newName.value : null,
          };

    router.post((isArtist ? assignArtists : assignTracks).url(), data, {
        preserveScroll: true,
        onStart: () => (submitting.value = true),
        onError: (validationErrors) => (errors.value = validationErrors),
        onSuccess: () => {
            open.value = false;
            emit('assigned');
        },
        onFinish: () => (submitting.value = false),
    });
}

const modeItems = computed(() => [
    { value: 'existing', label: trans('dj.bulk.existing') },
    { value: 'new', label: trans('dj.bulk.new') },
]);
</script>

<template>
    <UModal
        v-model:open="open"
        :title="
            record === 'artist'
                ? trans('dj.bulk.assign_artist_title')
                : trans('dj.bulk.assign_song_title')
        "
        :description="
            record === 'artist'
                ? trans('dj.bulk.assign_artist_help')
                : trans('dj.bulk.assign_song_help')
        "
    >
        <template #body>
            <div class="flex flex-col gap-4">
                <UAlert
                    v-if="record === 'track' && (artistCount ?? 0) > 1"
                    color="warning"
                    variant="subtle"
                    icon="i-lucide-users"
                    :description="
                        trans('dj.bulk.mixed_artists', {
                            count: artistCount ?? 0,
                        })
                    "
                />

                <URadioGroup
                    v-model="mode"
                    :items="modeItems"
                    orientation="horizontal"
                />

                <template v-if="mode === 'existing'">
                    <UInput
                        v-model="search"
                        icon="i-lucide-search"
                        class="w-full"
                        autofocus
                        :loading="searcher.processing"
                        :placeholder="
                            record === 'artist'
                                ? trans(
                                      'dj.canonical_artists.show.merge_search',
                                  )
                                : trans('dj.matching.track.merge_search')
                        "
                    />
                    <ul class="flex max-h-64 flex-col gap-1 overflow-y-auto">
                        <li v-for="option in results" :key="option.id">
                            <button
                                type="button"
                                class="hover:bg-elevated flex w-full flex-col rounded-md px-3 py-2 text-start"
                                :class="{
                                    'bg-elevated ring-primary ring-1 ring-inset':
                                        target?.id === option.id,
                                }"
                                @click="target = option"
                            >
                                <span
                                    class="text-highlighted text-sm font-medium"
                                    >{{ option.label }}</span
                                >
                                <span
                                    v-if="option.detail"
                                    class="text-muted text-xs"
                                    >{{ option.detail }}</span
                                >
                            </button>
                        </li>
                        <li
                            v-if="!results.length && !searcher.processing"
                            class="text-muted px-3 py-2 text-sm"
                        >
                            {{ trans('dj.matching.track.no_results') }}
                        </li>
                    </ul>
                </template>

                <UFormField
                    v-else
                    :label="
                        record === 'artist'
                            ? trans('dj.bulk.fields.new_artist_name')
                            : trans('dj.bulk.fields.new_song_title')
                    "
                    :error="errors.new_song_title ?? errors.new_artist_name"
                    required
                >
                    <UInput v-model="newName" class="w-full" autofocus />
                </UFormField>

                <p
                    v-if="errors.track_ids || errors.artist_ids"
                    class="text-error text-sm"
                >
                    {{ errors.track_ids ?? errors.artist_ids }}
                </p>
            </div>
        </template>

        <template #footer>
            <div class="flex w-full justify-end gap-2">
                <UButton
                    color="neutral"
                    variant="ghost"
                    :label="trans('common.actions.cancel')"
                    @click="open = false"
                />
                <UButton
                    :disabled="!canSubmit"
                    :loading="submitting"
                    :label="trans('dj.bulk.assign')"
                    @click="submit"
                />
            </div>
        </template>
    </UModal>
</template>

<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { search as searchArtists } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalArtistController';
import {
    index,
    show,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SongController';
import type { MusicalKeyData, SelectItem } from '@/types';

const props = defineProps<{
    song: {
        id: number;
        title: string;
        artist: { id: number; name: string } | null;
        bpm: number | null;
        duration: string | null;
        key: MusicalKeyData | null;
        is_verified: boolean;
    };
    keys: SelectItem[];
}>();

const { trans } = useTrans();

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
    })).submit(update(props.song.id));
}

const breadcrumbs = computed(() => [
    { label: trans('dj.songs.title'), to: index.url() },
    { label: props.song.title, to: show.url(props.song.id) },
    { label: trans('common.actions.edit') },
]);

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
</script>

<template>
    <EditorLayout
        :title="trans('dj.songs.edit.title', { title: song.title })"
        :breadcrumbs="breadcrumbs"
        :is-dirty="form.isDirty"
        :processing="form.processing"
        narrow
        @submit="save"
    >
        <template v-if="song.is_verified" #badges>
            <UBadge color="success" variant="subtle">
                {{ trans('dj.matching.verified') }}
            </UBadge>
        </template>

        <UPageCard
            :description="trans('dj.songs.show.edit_help')"
            variant="subtle"
        >
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
                            placeholder: trans('dj.songs.fields.artist_search'),
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
                    <USelect v-model="form.key" :items="keys" class="w-full" />
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
            </div>
        </UPageCard>
    </EditorLayout>
</template>

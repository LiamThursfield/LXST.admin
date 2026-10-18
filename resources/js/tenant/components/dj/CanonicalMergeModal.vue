<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { search as searchArtists } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalArtistController';
import { store as merge } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalMergeController';
import { search as searchTracks } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalTrackController';

/**
 * Picks another canonical track or artist (by searching) and merges this one into it
 */
const props = defineProps<{
    record: 'track' | 'artist';
    /** The record being merged away */
    sourceId: number;
    /** What the search starts with, e.g. the record's own name */
    initialSearch: string;
    title: string;
    description: string;
    placeholder: string;
}>();

const open = defineModel<boolean>('open', { default: false });

const { trans } = useTrans();

type Option = {
    id: number;
    label: string;
    detail: string | null;
    count: number;
};

const search = ref('');
const results = ref<Option[]>([]);
const target = ref<Option | null>(null);
const merging = ref(false);
const searcher = useHttp();

const find = useDebounceFn(async () => {
    const query = { search: search.value, except: String(props.sourceId) };

    try {
        if (props.record === 'artist') {
            const response = (await searcher.get(
                searchArtists.url({ query }),
            )) as {
                data: Array<{ id: number; name: string; tracks_count: number }>;
            };
            results.value = response.data.map((artist) => ({
                id: artist.id,
                label: artist.name,
                detail: null,
                count: artist.tracks_count,
            }));
        } else {
            const response = (await searcher.get(
                searchTracks.url({ query }),
            )) as {
                data: Array<{
                    id: number;
                    title: string;
                    artist: string | null;
                    sources_count: number;
                }>;
            };
            results.value = response.data.map((track) => ({
                id: track.id,
                label: track.title,
                detail: track.artist,
                count: track.sources_count,
            }));
        }
    } catch {
        results.value = [];
    }
}, 250);

watch(search, () => find());

watch(open, (isOpen) => {
    if (isOpen) {
        search.value = props.initialSearch;
        target.value = null;
        find();
    }
});

function submit() {
    if (!target.value) {
        return;
    }

    router.post(
        merge.url(),
        {
            record: props.record,
            target_id: target.value.id,
            source_id: props.sourceId,
        },
        {
            onStart: () => (merging.value = true),
            onFinish: () => {
                merging.value = false;
                open.value = false;
            },
        },
    );
}
</script>

<template>
    <UModal v-model:open="open" :title="title" :description="description">
        <template #body>
            <div class="flex flex-col gap-3">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    class="w-full"
                    autofocus
                    :loading="searcher.processing"
                    :placeholder="placeholder"
                />
                <ul class="flex max-h-72 flex-col gap-1 overflow-y-auto">
                    <li v-for="option in results" :key="option.id">
                        <button
                            type="button"
                            class="hover:bg-elevated flex w-full flex-col rounded-md px-3 py-2 text-start"
                            :class="{
                                'bg-elevated ring-primary ring-1':
                                    target?.id === option.id,
                            }"
                            @click="target = option"
                        >
                            <span class="text-highlighted text-sm font-medium">
                                {{ option.label }}
                            </span>
                            <span class="text-muted text-xs">
                                <template v-if="option.detail">
                                    {{ option.detail }} ·
                                </template>
                                {{
                                    record === 'artist'
                                        ? trans('dj.songs.total', {
                                              count: option.count,
                                          })
                                        : trans('dj.matching.sources', {
                                              count: option.count,
                                          })
                                }}
                            </span>
                        </button>
                    </li>
                    <li
                        v-if="!results.length && !searcher.processing"
                        class="text-muted px-3 py-2 text-sm"
                    >
                        {{ trans('dj.matching.track.no_results') }}
                    </li>
                </ul>
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
                    :disabled="!target"
                    :loading="merging"
                    :label="trans('dj.matching.track.merge_submit')"
                    @click="submit"
                />
            </div>
        </template>
    </UModal>
</template>

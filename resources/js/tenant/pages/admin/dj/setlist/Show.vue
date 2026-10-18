<script setup lang="ts">
import { Head, Link, useForm, useHttp } from '@inertiajs/vue3';
import { VueDraggable } from 'vue-draggable-plus';
import { search as searchSongs } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalTrackController';
import {
    destroy,
    duplicate,
    index,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SetlistController';
import {
    destroy as removeItem,
    reorder,
    store as addItem,
    update as updateItem,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SetlistItemController';
import type {
    SelectItem,
    SetlistData,
    SetlistItemData,
    SetlistSongType,
} from '@/types';

const props = defineProps<{
    setlist: { data: SetlistData };
    items: { data: SetlistItemData[] };
    gigs: SelectItem[];
    visibilities: SelectItem[];
    types: SelectItem[];
    canManage: boolean;
}>();

const { trans } = useTrans();
const setlist = computed(() => props.setlist.data);

const typeOrder: SetlistSongType[] = ['must_play', 'maybe', 'do_not_play'];
const typeColors: Record<SetlistSongType, 'success' | 'info' | 'error'> = {
    must_play: 'success',
    maybe: 'info',
    do_not_play: 'error',
};

// Songs grouped by type, each group in order; dragging reorders within a group
const groups = ref<Record<SetlistSongType, SetlistItemData[]>>({
    must_play: [],
    maybe: [],
    do_not_play: [],
});

watch(
    () => props.items.data,
    (items) => {
        groups.value = {
            must_play: items.filter((item) => item.type === 'must_play'),
            maybe: items.filter((item) => item.type === 'maybe'),
            do_not_play: items.filter((item) => item.type === 'do_not_play'),
        };
    },
    { immediate: true },
);

function saveOrder() {
    router.put(
        reorder.url(setlist.value.id),
        {
            item_ids: typeOrder.flatMap((type) =>
                groups.value[type].map((item) => item.id),
            ),
        },
        { preserveScroll: true, preserveState: true },
    );
}

function changeType(item: SetlistItemData, type: string) {
    router.patch(
        updateItem.url({ setlist: setlist.value.id, item: item.id }),
        { type },
        { preserveScroll: true },
    );
}

function saveNote(item: SetlistItemData, note: string) {
    if ((item.note ?? '') === note) {
        return;
    }

    router.patch(
        updateItem.url({ setlist: setlist.value.id, item: item.id }),
        { note },
        { preserveScroll: true },
    );
}

function remove(item: SetlistItemData) {
    router.delete(
        removeItem.url({ setlist: setlist.value.id, item: item.id }),
        { preserveScroll: true },
    );
}

// The setlist's details
const details = useForm({
    name: setlist.value.name,
    gig_id: setlist.value.gig?.id ?? null,
    visibility: setlist.value.visibility,
    description: setlist.value.description ?? '',
});

function saveDetails() {
    details.submit(update(setlist.value.id), { preserveScroll: true });
}

// Adding a song from the library, or a custom one
const mode = ref<'library' | 'custom'>('library');
const songSearch = ref('');
const songResults = ref<
    Array<{ id: number; title: string; artist: string | null }>
>([]);
const searcher = useHttp();
const addForm = useForm({
    canonical_track_id: null as number | null,
    custom_artist: '',
    custom_title: '',
    type: 'must_play' as SetlistSongType,
    note: '',
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
    addForm
        .transform((data) =>
            mode.value === 'library'
                ? {
                      canonical_track_id: data.canonical_track_id,
                      type: data.type,
                      note: data.note || null,
                  }
                : {
                      custom_artist: data.custom_artist,
                      custom_title: data.custom_title || null,
                      type: data.type,
                      note: data.note || null,
                  },
        )
        .submit(addItem(setlist.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                addForm.reset(
                    'canonical_track_id',
                    'custom_artist',
                    'custom_title',
                    'note',
                );
                songSearch.value = '';
            },
        });
}

const modeItems = computed(() => [
    { value: 'library', label: trans('dj.setlists.from_library') },
    { value: 'custom', label: trans('dj.setlists.custom') },
]);

const canAdd = computed(() =>
    mode.value === 'library'
        ? addForm.canonical_track_id !== null
        : addForm.custom_artist.trim() !== '',
);

// Duplicating and deleting
const duplicateOpen = ref(false);
const duplicateForm = useForm({ name: '', gig_id: null as number | null });

function openDuplicate() {
    duplicateForm.name = trans('dj.setlists.copy_of', {
        name: setlist.value.name,
    });
    duplicateForm.gig_id = null;
    duplicateOpen.value = true;
}

function runDuplicate() {
    duplicateForm.submit(duplicate(setlist.value.id), {
        onSuccess: () => (duplicateOpen.value = false),
    });
}

const deleteOpen = ref(false);

const gigItems = computed(() => [
    { value: null, label: trans('dj.setlists.no_gig') },
    ...props.gigs,
]);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="setlist.name">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
                <template #right>
                    <UButton
                        v-if="canManage"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-copy"
                        :label="trans('dj.setlists.duplicate')"
                        @click="openDuplicate"
                    />
                    <UButton
                        v-if="canManage"
                        color="error"
                        variant="ghost"
                        icon="i-lucide-trash"
                        :aria-label="trans('common.actions.delete')"
                        @click="deleteOpen = true"
                    />
                    <UButton
                        as="Link"
                        :href="index.url()"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-arrow-left"
                        :label="trans('dj.setlists.title')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="setlist.name" />

            <div
                class="mx-auto grid w-full max-w-6xl grid-cols-1 gap-6 lg:grid-cols-3"
            >
                <div class="flex flex-col gap-6 lg:col-span-2">
                    <UPageCard
                        v-if="canManage"
                        :title="trans('dj.setlists.add_song')"
                        variant="subtle"
                    >
                        <form class="flex flex-col gap-4" @submit.prevent="add">
                            <URadioGroup
                                v-model="mode"
                                :items="modeItems"
                                orientation="horizontal"
                            />

                            <template v-if="mode === 'library'">
                                <UInput
                                    v-model="songSearch"
                                    icon="i-lucide-search"
                                    class="w-full"
                                    :loading="searcher.processing"
                                    :placeholder="
                                        trans('dj.setlists.search_songs')
                                    "
                                />
                                <ul
                                    v-if="songSearch"
                                    class="flex max-h-56 flex-col gap-1 overflow-y-auto"
                                >
                                    <li
                                        v-for="song in songResults"
                                        :key="song.id"
                                    >
                                        <button
                                            type="button"
                                            class="hover:bg-elevated flex w-full flex-col rounded-md px-3 py-2 text-start"
                                            :class="{
                                                'bg-elevated ring-primary ring-1 ring-inset':
                                                    addForm.canonical_track_id ===
                                                    song.id,
                                            }"
                                            @click="
                                                addForm.canonical_track_id =
                                                    song.id
                                            "
                                        >
                                            <span
                                                class="text-highlighted text-sm font-medium"
                                                >{{ song.title }}</span
                                            >
                                            <span
                                                v-if="song.artist"
                                                class="text-muted text-xs"
                                                >{{ song.artist }}</span
                                            >
                                        </button>
                                    </li>
                                    <li
                                        v-if="
                                            !songResults.length &&
                                            !searcher.processing
                                        "
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
                            </template>

                            <div
                                v-else
                                class="grid grid-cols-1 gap-4 sm:grid-cols-2"
                            >
                                <UFormField
                                    :label="
                                        trans(
                                            'dj.setlists.fields.custom_artist',
                                        )
                                    "
                                    :error="addForm.errors.custom_artist"
                                    required
                                >
                                    <UInput
                                        v-model="addForm.custom_artist"
                                        class="w-full"
                                    />
                                </UFormField>
                                <UFormField
                                    :label="
                                        trans('dj.setlists.fields.custom_title')
                                    "
                                    :error="addForm.errors.custom_title"
                                >
                                    <UInput
                                        v-model="addForm.custom_title"
                                        class="w-full"
                                    />
                                </UFormField>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <UFormField
                                    :label="trans('dj.setlists.fields.type')"
                                    :error="addForm.errors.type"
                                >
                                    <USelect
                                        v-model="addForm.type"
                                        :items="types"
                                        class="w-full"
                                    />
                                </UFormField>
                                <UFormField
                                    :label="trans('dj.setlists.fields.note')"
                                    :error="addForm.errors.note"
                                    class="sm:col-span-2"
                                >
                                    <UInput
                                        v-model="addForm.note"
                                        class="w-full"
                                        :placeholder="
                                            trans(
                                                'dj.setlists.fields.note_placeholder',
                                            )
                                        "
                                    />
                                </UFormField>
                            </div>

                            <div class="flex justify-end">
                                <UButton
                                    type="submit"
                                    icon="i-lucide-plus"
                                    :disabled="!canAdd"
                                    :loading="addForm.processing"
                                    :label="trans('dj.setlists.add')"
                                />
                            </div>
                        </form>
                    </UPageCard>

                    <UPageCard
                        v-for="type in typeOrder"
                        :key="type"
                        variant="subtle"
                    >
                        <template #title>
                            <span class="flex items-center gap-2">
                                <UBadge
                                    :color="typeColors[type]"
                                    variant="subtle"
                                >
                                    {{
                                        types.find(
                                            (item) => item.value === type,
                                        )?.label
                                    }}
                                </UBadge>
                                <span class="text-muted text-sm">{{
                                    groups[type].length
                                }}</span>
                            </span>
                        </template>

                        <p
                            v-if="!groups[type].length"
                            class="text-muted text-sm"
                        >
                            {{ trans('dj.setlists.no_songs') }}
                        </p>

                        <VueDraggable
                            v-model="groups[type]"
                            handle=".drag-handle"
                            :disabled="!canManage"
                            class="divide-default flex flex-col divide-y"
                            @end="saveOrder"
                        >
                            <div
                                v-for="item in groups[type]"
                                :key="item.id"
                                class="flex items-start gap-3 py-2"
                            >
                                <UIcon
                                    v-if="canManage"
                                    name="i-lucide-grip-vertical"
                                    class="drag-handle text-muted mt-1 size-4 shrink-0 cursor-grab"
                                    :aria-label="trans('dj.setlists.drag')"
                                />
                                <div class="flex min-w-0 flex-1 flex-col gap-1">
                                    <span
                                        class="flex flex-wrap items-center gap-x-2"
                                    >
                                        <Link
                                            v-if="item.song_url"
                                            :href="item.song_url"
                                            class="text-highlighted font-medium hover:underline"
                                        >
                                            {{ item.title }}
                                        </Link>
                                        <span
                                            v-else
                                            class="text-highlighted font-medium"
                                            >{{ item.title || '—' }}</span
                                        >
                                        <span class="text-muted text-sm">{{
                                            item.artist
                                        }}</span>
                                        <UBadge
                                            v-if="item.is_custom"
                                            color="neutral"
                                            variant="outline"
                                            size="sm"
                                        >
                                            {{ trans('dj.setlists.custom') }}
                                        </UBadge>
                                    </span>
                                    <span class="text-muted text-xs">
                                        {{
                                            [
                                                item.bpm,
                                                item.key?.camelot,
                                                item.duration,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')
                                        }}
                                        <template v-if="item.added_by">
                                            ·
                                            {{
                                                trans('dj.setlists.added_by', {
                                                    name: item.added_by,
                                                })
                                            }}
                                        </template>
                                    </span>
                                    <UInput
                                        v-if="canManage"
                                        :model-value="item.note ?? ''"
                                        size="sm"
                                        variant="ghost"
                                        class="w-full"
                                        :placeholder="
                                            trans(
                                                'dj.setlists.fields.note_placeholder',
                                            )
                                        "
                                        :aria-label="
                                            trans('dj.setlists.fields.note')
                                        "
                                        @change="
                                            (event: Event) =>
                                                saveNote(
                                                    item,
                                                    (
                                                        event.target as HTMLInputElement
                                                    ).value,
                                                )
                                        "
                                    />
                                    <span
                                        v-else-if="item.note"
                                        class="text-sm"
                                        >{{ item.note }}</span
                                    >
                                </div>
                                <div
                                    v-if="canManage"
                                    class="flex shrink-0 items-center gap-1"
                                >
                                    <USelect
                                        :model-value="item.type ?? undefined"
                                        :items="types"
                                        size="sm"
                                        class="w-32"
                                        :aria-label="
                                            trans('dj.setlists.fields.type')
                                        "
                                        @update:model-value="
                                            (value) =>
                                                changeType(item, String(value))
                                        "
                                    />
                                    <UButton
                                        icon="i-lucide-x"
                                        size="sm"
                                        color="neutral"
                                        variant="ghost"
                                        :aria-label="
                                            trans('dj.setlists.remove')
                                        "
                                        @click="remove(item)"
                                    />
                                </div>
                            </div>
                        </VueDraggable>
                    </UPageCard>
                </div>

                <UPageCard
                    :title="trans('dj.setlists.details')"
                    variant="subtle"
                    class="h-fit"
                >
                    <form
                        class="flex flex-col gap-4"
                        @submit.prevent="saveDetails"
                    >
                        <fieldset
                            :disabled="!canManage"
                            class="flex flex-col gap-4"
                        >
                            <UFormField
                                :label="trans('dj.setlists.fields.name')"
                                :error="details.errors.name"
                                required
                            >
                                <UInput v-model="details.name" class="w-full" />
                            </UFormField>
                            <UFormField
                                :label="trans('dj.setlists.fields.gig')"
                                :error="details.errors.gig_id"
                            >
                                <USelectMenu
                                    v-model="details.gig_id"
                                    :items="gigItems"
                                    value-key="value"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                :label="trans('dj.setlists.fields.visibility')"
                                :help="
                                    trans('dj.setlists.fields.visibility_help')
                                "
                                :error="details.errors.visibility"
                            >
                                <USelect
                                    v-model="details.visibility"
                                    :items="visibilities"
                                    class="w-full"
                                />
                            </UFormField>
                            <UFormField
                                :label="trans('dj.setlists.fields.description')"
                                :error="details.errors.description"
                            >
                                <UTextarea
                                    v-model="details.description"
                                    :rows="3"
                                    class="w-full"
                                />
                            </UFormField>
                        </fieldset>
                        <div v-if="canManage" class="flex justify-end">
                            <UButton
                                type="submit"
                                :loading="details.processing"
                                :label="trans('dj.setlists.save')"
                            />
                        </div>
                    </form>
                </UPageCard>
            </div>

            <UModal
                v-model:open="duplicateOpen"
                :title="trans('dj.setlists.duplicate_title')"
                :description="trans('dj.setlists.duplicate_help')"
            >
                <template #body>
                    <form
                        id="setlist-duplicate"
                        class="flex flex-col gap-4"
                        @submit.prevent="runDuplicate"
                    >
                        <UFormField
                            :label="trans('dj.setlists.fields.name')"
                            :error="duplicateForm.errors.name"
                            required
                        >
                            <UInput
                                v-model="duplicateForm.name"
                                class="w-full"
                                autofocus
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.setlists.fields.gig')"
                            :error="duplicateForm.errors.gig_id"
                        >
                            <USelectMenu
                                v-model="duplicateForm.gig_id"
                                :items="gigItems"
                                value-key="value"
                                class="w-full"
                            />
                        </UFormField>
                    </form>
                </template>
                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="duplicateOpen = false"
                        />
                        <UButton
                            type="submit"
                            form="setlist-duplicate"
                            :loading="duplicateForm.processing"
                            :label="trans('dj.setlists.duplicate')"
                        />
                    </div>
                </template>
            </UModal>

            <UModal
                v-model:open="deleteOpen"
                :title="trans('common.actions.delete')"
            >
                <template #body>
                    <p class="text-sm">
                        {{
                            trans('dj.setlists.confirm_delete', {
                                name: setlist.name,
                            })
                        }}
                    </p>
                </template>
                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="deleteOpen = false"
                        />
                        <UButton
                            color="error"
                            :label="trans('common.actions.delete')"
                            @click="router.delete(destroy.url(setlist.id))"
                        />
                    </div>
                </template>
            </UModal>
        </template>
    </UDashboardPanel>
</template>

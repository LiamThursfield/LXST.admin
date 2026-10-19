<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    show as showSetlist,
    songs as searchSongs,
} from '@/actions/App/Http/Controllers/Tenant/Api/V1/Dj/GuestSetlistController';
import {
    destroy as removeItem,
    store as addItem,
    update as updateItem,
} from '@/actions/App/Http/Controllers/Tenant/Api/V1/Dj/GuestSetlistItemController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { PublicApiError, publicApi, useGuestToken } from '@/lib/publicApi';
import { formatDateTime } from '@/lib/timezone';
import GuestPassForm from '@/tenant/components/dj/public/GuestPassForm.vue';
import type {
    GuestSetlistData,
    PublicSongData,
    SetlistSongType,
} from '@/types';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    /** The setlist's public ID */
    setlistId: string;
}>();

const { trans } = useTrans();
const toast = useToast();

const guestToken = useGuestToken(() => props.setlistId);
const setlist = ref<GuestSetlistData | null>(null);

async function load() {
    if (!guestToken.value) {
        return;
    }

    try {
        const response = await publicApi<{ data: GuestSetlistData }>(
            showSetlist(props.setlistId),
            { guestToken: guestToken.value },
        );
        setlist.value = response.data;
    } catch (exception) {
        // The pass was revoked or the token ran out: ask for the password again
        if (exception instanceof PublicApiError && exception.status === 401) {
            guestToken.value = null;
        }
    }
}

onMounted(load);
watch(guestToken, (token) => token && load());

const groups = computed(() =>
    (setlist.value?.types ?? []).map((type) => ({
        ...type,
        items: (setlist.value?.items ?? []).filter(
            (item) => item.type === type.value,
        ),
    })),
);

// Adding a song: from the library, or typed in
const mode = ref<'library' | 'custom'>('library');
const search = ref('');
const results = ref<PublicSongData[]>([]);
const chosen = ref<PublicSongData | null>(null);
const form = reactive({
    custom_artist: '',
    custom_title: '',
    type: 'must_play' as SetlistSongType,
    note: '',
    guest_name: '',
});
const errors = ref<Record<string, string[]>>({});
const adding = ref(false);

const findSongs = useDebounceFn(async () => {
    if (search.value.trim().length < 2) {
        results.value = [];

        return;
    }

    try {
        const response = await publicApi<{ data: PublicSongData[] }>(
            searchSongs(
                { songList: props.setlistId },
                { query: { search: search.value } },
            ),
            { guestToken: guestToken.value },
        );
        results.value = response.data;
    } catch {
        results.value = [];
    }
}, 300);

watch(search, () => findSongs());

async function add() {
    adding.value = true;
    errors.value = {};

    try {
        await publicApi(addItem(props.setlistId), {
            guestToken: guestToken.value,
            body: {
                ...(mode.value === 'library'
                    ? { canonical_track_id: chosen.value?.id }
                    : {
                          custom_artist: form.custom_artist,
                          custom_title: form.custom_title || null,
                      }),
                type: form.type,
                note: form.note || null,
                guest_name: form.guest_name || null,
            },
        });
        toast.add({
            title: trans('dj.hosted.setlist.added'),
            icon: 'i-lucide-check',
            color: 'success',
        });
        chosen.value = null;
        search.value = '';
        form.custom_artist = '';
        form.custom_title = '';
        form.note = '';
        await load();
    } catch (exception) {
        if (exception instanceof PublicApiError && exception.body.errors) {
            errors.value = exception.body.errors;
        } else {
            toast.add({
                title:
                    exception instanceof PublicApiError
                        ? exception.message
                        : trans('dj.hosted.error'),
                color: 'error',
            });
        }
    } finally {
        adding.value = false;
    }
}

async function retype(
    item: GuestSetlistData['items'][number],
    type: SetlistSongType,
) {
    await publicApi(updateItem({ songList: props.setlistId, item: item.id }), {
        guestToken: guestToken.value,
        body: { type, note: item.note },
    }).catch(() =>
        toast.add({ title: trans('dj.hosted.error'), color: 'error' }),
    );
    await load();
}

async function remove(item: GuestSetlistData['items'][number]) {
    await publicApi(removeItem({ songList: props.setlistId, item: item.id }), {
        guestToken: guestToken.value,
    }).catch(() =>
        toast.add({ title: trans('dj.hosted.error'), color: 'error' }),
    );
    await load();
}

const canAdd = computed(() =>
    mode.value === 'library'
        ? chosen.value !== null
        : form.custom_artist.trim() !== '',
);
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="setlist?.name ?? trans('dj.setlists.title')" />

        <GuestPassForm
            v-if="!guestToken"
            :list-id="setlistId"
            @token="(token) => (guestToken = token)"
        />

        <p v-else-if="!setlist" class="text-muted">
            {{ trans('dj.hosted.loading') }}
        </p>

        <template v-else>
            <div class="flex flex-col gap-1">
                <h1 class="text-highlighted text-2xl font-bold">
                    {{ setlist.name }}
                </h1>
                <p v-if="setlist.gig" class="text-muted">
                    {{ setlist.gig.name }} ·
                    {{
                        formatDateTime(
                            setlist.gig.starts_at,
                            setlist.gig.timezone,
                        )
                    }}
                </p>
                <p
                    v-if="setlist.description"
                    class="whitespace-pre-line text-sm"
                >
                    {{ setlist.description }}
                </p>
            </div>

            <UPageCard
                v-if="setlist.pass.can_add"
                :title="trans('dj.hosted.setlist.add_title')"
                variant="subtle"
            >
                <form class="flex flex-col gap-4" @submit.prevent="add">
                    <template v-if="mode === 'library'">
                        <div
                            v-if="chosen"
                            class="bg-elevated flex items-center justify-between gap-3 rounded-md p-3"
                        >
                            <div class="flex min-w-0 flex-col">
                                <span class="text-highlighted font-medium">{{
                                    chosen.title
                                }}</span>
                                <span class="text-muted text-sm">{{
                                    chosen.artist
                                }}</span>
                            </div>
                            <UButton
                                color="neutral"
                                variant="ghost"
                                :label="trans('dj.hosted.request.change')"
                                @click="chosen = null"
                            />
                        </div>
                        <template v-else>
                            <UInput
                                v-model="search"
                                size="xl"
                                icon="i-lucide-search"
                                class="w-full"
                                :placeholder="trans('dj.hosted.request.search')"
                            />
                            <ul
                                v-if="search.trim().length >= 2"
                                class="flex flex-col gap-1"
                            >
                                <li v-for="song in results" :key="song.id">
                                    <button
                                        type="button"
                                        class="hover:bg-elevated flex w-full flex-col rounded-md px-3 py-2 text-start"
                                        @click="chosen = song"
                                    >
                                        <span
                                            class="text-highlighted font-medium"
                                            >{{ song.title }}</span
                                        >
                                        <span class="text-muted text-sm">{{
                                            song.artist
                                        }}</span>
                                    </button>
                                </li>
                                <li
                                    v-if="!results.length"
                                    class="text-muted px-3 py-2 text-sm"
                                >
                                    {{ trans('dj.hosted.request.no_results') }}
                                </li>
                            </ul>
                        </template>
                    </template>
                    <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <UFormField
                            :label="trans('dj.setlists.fields.custom_artist')"
                            :error="errors.custom_artist?.[0]"
                            required
                        >
                            <UInput
                                v-model="form.custom_artist"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.setlists.fields.custom_title')"
                        >
                            <UInput
                                v-model="form.custom_title"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                    <UButton
                        variant="link"
                        class="w-fit px-0"
                        :label="
                            mode === 'library'
                                ? trans('dj.hosted.setlist.custom')
                                : trans('dj.hosted.request.library')
                        "
                        @click="
                            mode = mode === 'library' ? 'custom' : 'library'
                        "
                    />

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <UFormField :label="trans('dj.hosted.setlist.type')">
                            <USelect
                                v-model="form.type"
                                :items="setlist.types"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                        <UFormField
                            :label="trans('dj.hosted.setlist.guest_name')"
                        >
                            <UInput
                                v-model="form.guest_name"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                    <UFormField
                        :label="trans('dj.hosted.setlist.note')"
                        :error="errors.note?.[0]"
                    >
                        <UInput
                            v-model="form.note"
                            size="lg"
                            class="w-full"
                            :placeholder="
                                trans('dj.setlists.fields.note_placeholder')
                            "
                        />
                    </UFormField>
                    <UButton
                        type="submit"
                        size="xl"
                        block
                        :loading="adding"
                        :disabled="!canAdd"
                        :label="trans('dj.hosted.setlist.add')"
                    />
                </form>
            </UPageCard>
            <UAlert
                v-else
                color="neutral"
                variant="subtle"
                icon="i-lucide-lock"
                :title="trans('dj.hosted.setlist.view_only')"
            />

            <UPageCard
                v-for="group in groups"
                :key="String(group.value)"
                :title="group.label"
                variant="subtle"
            >
                <ul
                    v-if="group.items.length"
                    class="divide-default flex flex-col divide-y"
                >
                    <li
                        v-for="item in group.items"
                        :key="item.id"
                        class="flex flex-wrap items-center justify-between gap-3 py-3"
                    >
                        <div class="flex min-w-0 flex-col">
                            <span class="text-highlighted font-medium">{{
                                item.title || item.artist
                            }}</span>
                            <span class="text-muted text-sm">
                                <template v-if="item.title">{{
                                    item.artist
                                }}</template>
                                <template v-if="item.is_mine">
                                    · {{ trans('dj.hosted.setlist.yours') }}
                                </template>
                                <template v-else-if="item.added_by">
                                    · {{ item.added_by }}
                                </template>
                            </span>
                            <span v-if="item.note" class="text-sm">{{
                                item.note
                            }}</span>
                        </div>
                        <div
                            v-if="item.is_mine"
                            class="flex items-center gap-2"
                        >
                            <USelect
                                :model-value="item.type ?? undefined"
                                :items="setlist.types"
                                size="sm"
                                class="w-36"
                                @update:model-value="
                                    (type) =>
                                        retype(item, type as SetlistSongType)
                                "
                            />
                            <UButton
                                size="sm"
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-x"
                                :aria-label="trans('dj.hosted.setlist.remove')"
                                @click="remove(item)"
                            />
                        </div>
                    </li>
                </ul>
                <p v-else class="text-muted text-sm">
                    {{ trans('dj.hosted.setlist.empty') }}
                </p>
            </UPageCard>
        </template>
    </div>
</template>

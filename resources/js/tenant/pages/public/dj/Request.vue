<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    current as currentGig,
    show as showGig,
} from '@/actions/App/Http/Controllers/Tenant/Api/V1/Dj/GigController';
import { store as storeRequest } from '@/actions/App/Http/Controllers/Tenant/Api/V1/Dj/SongRequestController';
import { index as searchTracks } from '@/actions/App/Http/Controllers/Tenant/Api/V1/Dj/TrackController';
import PublicLayout from '@/layouts/PublicLayout.vue';
import { PublicApiError, publicApi, useGuestToken } from '@/lib/publicApi';
import { formatDateTime } from '@/lib/timezone';
import GuestPassForm from '@/tenant/components/dj/public/GuestPassForm.vue';
import PublicRequestList from '@/tenant/components/dj/public/PublicRequestList.vue';
import type { PublicGigData, PublicSongData } from '@/types';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    /** A gig's public ID, or null for the current gig */
    gigId: string | null;
}>();

const { trans } = useTrans();

const gig = ref<PublicGigData | null>(null);
const loading = ref(true);
const failed = ref(false);

async function loadGig() {
    try {
        const response = await publicApi<{ data: PublicGigData | null }>(
            props.gigId ? showGig(props.gigId) : currentGig(),
        );
        gig.value = response.data;
        failed.value = false;
    } catch {
        failed.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(loadGig);

const guestToken = useGuestToken(() => gig.value?.requests.list_id);
const needsPass = computed(
    () => gig.value?.requests.requires_pass === true && !guestToken.value,
);

// Choosing a song: from library search, or typed in
const mode = ref<'library' | 'custom'>('library');
const search = ref('');
const results = ref<PublicSongData[]>([]);
const searching = ref(false);
const chosen = ref<PublicSongData | null>(null);

const findSongs = useDebounceFn(async () => {
    if (search.value.trim().length < 2 || !gig.value) {
        results.value = [];

        return;
    }

    searching.value = true;

    try {
        const response = await publicApi<{ data: PublicSongData[] }>(
            searchTracks({
                query: { search: search.value, gig: gig.value.id },
            }),
        );
        results.value = response.data;
    } catch {
        results.value = [];
    } finally {
        searching.value = false;
    }
}, 300);

watch(search, () => findSongs());

// The request itself
const form = reactive({
    custom_artist: '',
    custom_title: '',
    requester_name: '',
    show_name: false,
    message: '',
    honeypot: '',
});
const submitting = ref(false);
const result = ref<{ message: string; ok: boolean } | null>(null);
const errors = ref<Record<string, string[]>>({});
const list = ref<InstanceType<typeof PublicRequestList> | null>(null);

const canSubmit = computed(() =>
    mode.value === 'library'
        ? chosen.value !== null
        : form.custom_artist.trim() !== '',
);

async function submit() {
    if (!gig.value) {
        return;
    }

    const spam = gig.value.requests.spam_protection;
    submitting.value = true;
    errors.value = {};

    try {
        const response = await publicApi<{ data: { message: string } }>(
            storeRequest(gig.value.id),
            {
                guestToken: guestToken.value,
                body: {
                    ...(mode.value === 'library'
                        ? { canonical_track_id: chosen.value?.id }
                        : {
                              custom_artist: form.custom_artist,
                              custom_title: form.custom_title || null,
                          }),
                    requester_name: form.requester_name || null,
                    show_name: form.show_name,
                    message: form.message || null,
                    [spam.honeypot_field]: form.honeypot,
                    [spam.token_field]: spam.token,
                },
            },
        );
        result.value = { message: response.data.message, ok: true };
        list.value?.load();
    } catch (exception) {
        if (exception instanceof PublicApiError && exception.body.errors) {
            errors.value = exception.body.errors;
        } else {
            result.value = {
                message:
                    exception instanceof PublicApiError
                        ? exception.message
                        : trans('dj.hosted.error'),
                ok: false,
            };
        }

        // A pass that's run out needs entering again
        if (
            exception instanceof PublicApiError &&
            exception.body.reason === 'pass_required'
        ) {
            guestToken.value = null;
        }
    } finally {
        submitting.value = false;
    }
}

function another() {
    result.value = null;
    chosen.value = null;
    search.value = '';
    form.custom_artist = '';
    form.custom_title = '';
    form.message = '';
    // A fresh spam token, as the form has been "loaded" again
    loadGig();
}
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="gig?.name ?? trans('dj.hosted.request.title')" />

        <p v-if="loading" class="text-muted">
            {{ trans('dj.hosted.loading') }}
        </p>
        <p v-else-if="failed" class="text-muted">
            {{ trans('dj.hosted.error') }}
        </p>
        <p v-else-if="!gig" class="text-muted text-lg">
            {{ trans('dj.hosted.request.no_gig') }}
        </p>

        <template v-else>
            <div class="flex flex-col gap-1">
                <h1 class="text-highlighted text-2xl font-bold">
                    {{ gig.name }}
                </h1>
                <p class="text-muted">
                    <template v-if="gig.venue">{{ gig.venue }} · </template>
                    {{ formatDateTime(gig.starts_at, gig.timezone) }}
                </p>
            </div>

            <GuestPassForm
                v-if="needsPass && gig.requests.list_id"
                :list-id="gig.requests.list_id"
                @token="(token) => (guestToken = token)"
            />

            <template v-else>
                <UAlert
                    v-if="!gig.requests.open"
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-clock"
                    :title="trans('dj.hosted.request.closed')"
                    :description="
                        gig.requests.opens_at &&
                        new Date(gig.requests.opens_at) > new Date()
                            ? trans('dj.hosted.request.opens_at', {
                                  time: formatDateTime(
                                      gig.requests.opens_at,
                                      gig.timezone,
                                  ),
                              })
                            : undefined
                    "
                />

                <UPageCard
                    v-else
                    :title="trans('dj.hosted.request.title')"
                    variant="subtle"
                >
                    <div v-if="result" class="flex flex-col gap-3">
                        <UAlert
                            :color="result.ok ? 'success' : 'warning'"
                            variant="subtle"
                            :icon="
                                result.ok
                                    ? 'i-lucide-check'
                                    : 'i-lucide-circle-alert'
                            "
                            :title="result.message"
                        />
                        <UButton
                            size="lg"
                            color="neutral"
                            variant="outline"
                            :label="trans('dj.hosted.request.another')"
                            @click="another"
                        />
                    </div>

                    <form
                        v-else
                        class="flex flex-col gap-4"
                        @submit.prevent="submit"
                    >
                        <template v-if="mode === 'library'">
                            <div
                                v-if="chosen"
                                class="bg-elevated flex items-center justify-between gap-3 rounded-md p-3"
                            >
                                <div class="flex min-w-0 flex-col">
                                    <span class="text-muted text-xs">{{
                                        trans('dj.hosted.request.chosen')
                                    }}</span>
                                    <span
                                        class="text-highlighted font-medium"
                                        >{{ chosen.title }}</span
                                    >
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
                                    :loading="searching"
                                    :placeholder="
                                        trans('dj.hosted.request.search')
                                    "
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
                                        v-if="!results.length && !searching"
                                        class="text-muted px-3 py-2 text-sm"
                                    >
                                        {{
                                            trans(
                                                'dj.hosted.request.no_results',
                                            )
                                        }}
                                    </li>
                                </ul>
                            </template>
                            <p
                                v-if="errors.canonical_track_id"
                                class="text-error text-sm"
                            >
                                {{ errors.canonical_track_id[0] }}
                            </p>
                        </template>

                        <div
                            v-else
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2"
                        >
                            <UFormField
                                :label="
                                    trans('dj.requests.fields.custom_artist')
                                "
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
                                :label="
                                    trans('dj.requests.fields.custom_title')
                                "
                                :error="errors.custom_title?.[0]"
                            >
                                <UInput
                                    v-model="form.custom_title"
                                    size="lg"
                                    class="w-full"
                                />
                            </UFormField>
                        </div>

                        <UButton
                            v-if="gig.requests.allow_custom_requests"
                            variant="link"
                            class="w-fit px-0"
                            :label="
                                mode === 'library'
                                    ? trans('dj.hosted.request.custom')
                                    : trans('dj.hosted.request.library')
                            "
                            @click="
                                mode = mode === 'library' ? 'custom' : 'library'
                            "
                        />

                        <UFormField
                            :label="trans('dj.hosted.request.your_name')"
                            :error="errors.requester_name?.[0]"
                        >
                            <UInput
                                v-model="form.requester_name"
                                size="lg"
                                class="w-full"
                            />
                        </UFormField>
                        <UCheckbox
                            v-if="
                                form.requester_name &&
                                gig.requests.show_public_list
                            "
                            v-model="form.show_name"
                            :label="trans('dj.hosted.request.show_name')"
                        />
                        <UFormField
                            :label="trans('dj.hosted.request.message')"
                            :error="errors.message?.[0]"
                        >
                            <UTextarea
                                v-model="form.message"
                                :rows="2"
                                class="w-full"
                            />
                        </UFormField>

                        <!-- Left empty by people; bots fill it in -->
                        <input
                            v-model="form.honeypot"
                            type="text"
                            class="hidden"
                            tabindex="-1"
                            autocomplete="off"
                            aria-hidden="true"
                        />

                        <UButton
                            type="submit"
                            size="xl"
                            block
                            :loading="submitting"
                            :disabled="!canSubmit"
                            :label="trans('dj.hosted.request.submit')"
                        />
                    </form>
                </UPageCard>

                <PublicRequestList
                    v-if="gig.requests.show_public_list"
                    ref="list"
                    :gig-id="gig.id"
                    :guest-token="guestToken"
                />
            </template>
        </template>
    </div>
</template>

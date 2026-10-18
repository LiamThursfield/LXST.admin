<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { verify } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalTrackController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryTrackController';
import { store as split } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/LibraryTrackSplitController';
import { show as showSong } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SongController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import CanonicalMergeModal from '@/tenant/components/dj/CanonicalMergeModal.vue';
import type { LibraryTrackDetailData } from '@/types';

const props = defineProps<{
    track: { data: LibraryTrackDetailData };
    canManageMatching: boolean;
}>();

const detail = computed(() => props.track.data);

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);
const none = trans('dj.tracks.show.none');

const busy = ref(false);
const splitOpen = ref(false);

function setVerified(verified: boolean) {
    if (!detail.value.canonical) {
        return;
    }

    router.patch(
        verify.url(detail.value.canonical.id),
        { verified },
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => (busy.value = false),
        },
    );
}

function splitOff() {
    router.post(
        split.url(detail.value.id),
        {},
        {
            preserveScroll: true,
            onStart: () => (busy.value = true),
            onFinish: () => {
                busy.value = false;
                splitOpen.value = false;
            },
        },
    );
}

const mergeOpen = ref(false);

const details = computed(() =>
    [
        ['album', detail.value.album],
        ['label', detail.value.label],
        ['year', detail.value.year],
        ['genre', detail.value.genre],
        ['bpm', detail.value.bpm],
        ['duration', detail.value.duration],
        [
            'rating',
            detail.value.rating ? '★'.repeat(detail.value.rating) : null,
        ],
        ['play_count', detail.value.play_count],
        ['date_added', detail.value.date_added],
        ['service', detail.value.service],
        ['library', detail.value.library],
        ['source_id', detail.value.source_track_id],
    ].map(([key, value]) => ({
        label: trans(`dj.tracks.show.${key}`),
        value: value === null || value === '' ? none : String(value),
    })),
);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="detail.title">
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
                        :label="trans('dj.tracks.show.back')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="detail.title" />

            <div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
                <div class="flex flex-col gap-1">
                    <h1 class="text-highlighted text-2xl font-semibold">
                        {{ detail.title }}
                    </h1>
                    <p class="text-muted">
                        {{
                            detail.artist?.name ??
                            trans('dj.tracks.unknown_artist')
                        }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <UBadge
                            v-if="detail.key"
                            color="primary"
                            variant="subtle"
                        >
                            {{ detail.key.label }}
                        </UBadge>
                        <UBadge
                            v-for="tag in detail.tags"
                            :key="tag.name"
                            color="neutral"
                            variant="subtle"
                        >
                            {{
                                tag.category
                                    ? `${tag.category}: ${tag.name}`
                                    : tag.name
                            }}
                        </UBadge>
                    </div>
                </div>

                <UAlert
                    v-if="detail.missing_since"
                    color="warning"
                    variant="subtle"
                    icon="i-lucide-circle-alert"
                    :description="
                        trans('dj.tracks.show.missing', {
                            date: formatDateTime(
                                detail.missing_since,
                                timezone,
                            ),
                        })
                    "
                />

                <UPageCard
                    :title="trans('dj.tracks.show.details')"
                    variant="subtle"
                >
                    <dl
                        class="grid grid-cols-1 gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div v-for="detail in details" :key="detail.label">
                            <dt class="text-muted text-xs">
                                {{ detail.label }}
                            </dt>
                            <dd class="break-words text-sm">
                                {{ detail.value }}
                            </dd>
                        </div>
                        <div v-if="detail.key">
                            <dt class="text-muted text-xs">
                                {{ trans('dj.tracks.show.key') }}
                            </dt>
                            <dd class="text-sm">
                                {{
                                    trans('dj.tracks.show.key_notations', {
                                        camelot: detail.key.camelot,
                                        open_key: detail.key.open_key,
                                        standard: detail.key.standard,
                                    })
                                }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2 lg:col-span-3">
                            <dt class="text-muted text-xs">
                                {{ trans('dj.tracks.show.location') }}
                            </dt>
                            <dd class="break-all font-mono text-sm">
                                {{ detail.location ?? none }}
                            </dd>
                        </div>
                        <div
                            v-if="detail.comments"
                            class="sm:col-span-2 lg:col-span-3"
                        >
                            <dt class="text-muted text-xs">
                                {{ trans('dj.tracks.show.comments') }}
                            </dt>
                            <dd class="whitespace-pre-line text-sm">
                                {{ detail.comments }}
                            </dd>
                        </div>
                    </dl>
                </UPageCard>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <UPageCard
                        :title="trans('dj.tracks.show.canonical')"
                        :description="trans('dj.tracks.show.canonical_help')"
                        variant="subtle"
                    >
                        <div
                            v-if="detail.canonical"
                            class="flex flex-col gap-1 text-sm"
                        >
                            <Link
                                :href="showSong.url(detail.canonical.id)"
                                class="text-highlighted font-medium hover:underline"
                            >
                                {{ detail.canonical.title }}
                            </Link>
                            <span class="text-muted">
                                {{
                                    detail.canonical.artist ??
                                    trans('dj.tracks.unknown_artist')
                                }}
                            </span>
                            <span class="text-muted">
                                {{
                                    [
                                        detail.canonical.bpm,
                                        detail.canonical.key?.camelot,
                                        detail.canonical.duration,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </span>
                            <UBadge
                                :color="
                                    detail.canonical.is_verified
                                        ? 'success'
                                        : 'neutral'
                                "
                                variant="subtle"
                                class="mt-1 w-fit"
                            >
                                {{
                                    detail.canonical.is_verified
                                        ? trans('dj.tracks.show.verified')
                                        : trans('dj.tracks.show.unverified')
                                }}
                            </UBadge>
                        </div>
                        <p v-else class="text-muted text-sm">
                            {{ trans('dj.tracks.show.no_canonical') }}
                        </p>

                        <div
                            v-if="canManageMatching && detail.canonical"
                            class="mt-2 flex flex-wrap gap-2"
                        >
                            <UButton
                                size="sm"
                                color="neutral"
                                variant="outline"
                                :icon="
                                    detail.canonical.is_verified
                                        ? 'i-lucide-shield-off'
                                        : 'i-lucide-shield-check'
                                "
                                :label="
                                    detail.canonical.is_verified
                                        ? trans('dj.matching.track.unverify')
                                        : trans('dj.matching.track.verify')
                                "
                                :title="trans('dj.matching.track.verify_help')"
                                :disabled="busy"
                                @click="
                                    setVerified(!detail.canonical.is_verified)
                                "
                            />
                            <UButton
                                size="sm"
                                color="neutral"
                                variant="outline"
                                icon="i-lucide-merge"
                                :label="trans('dj.matching.track.merge')"
                                :disabled="busy"
                                @click="mergeOpen = true"
                            />
                            <UButton
                                size="sm"
                                color="neutral"
                                variant="outline"
                                icon="i-lucide-split"
                                :label="trans('dj.matching.track.split')"
                                :disabled="busy"
                                @click="splitOpen = true"
                            />
                        </div>

                        <USeparator class="my-2" />

                        <h3 class="text-highlighted text-sm font-medium">
                            {{ trans('dj.tracks.show.other_sources') }}
                        </h3>
                        <ul
                            v-if="detail.other_sources.length"
                            class="flex flex-col gap-1 text-sm"
                        >
                            <li
                                v-for="other in detail.other_sources"
                                :key="other.id"
                            >
                                <Link :href="other.url" class="hover:underline">
                                    {{ other.title }}
                                </Link>
                                <span class="text-muted">
                                    · {{ other.library }} ({{ other.source }})
                                </span>
                            </li>
                        </ul>
                        <p v-else class="text-muted text-sm">
                            {{ trans('dj.tracks.show.no_other_sources') }}
                        </p>
                    </UPageCard>

                    <UPageCard
                        :title="trans('dj.tracks.show.history')"
                        variant="subtle"
                    >
                        <template v-if="detail.history_plays_count">
                            <p class="text-sm">
                                {{
                                    trans('dj.tracks.show.history_count', {
                                        count: detail.history_plays_count,
                                    })
                                }}
                                <span
                                    v-if="
                                        detail.history_plays_count >
                                        detail.history_plays.length
                                    "
                                    class="text-muted"
                                >
                                    {{
                                        trans('dj.tracks.show.history_more', {
                                            shown: detail.history_plays.length,
                                        })
                                    }}
                                </span>
                            </p>
                            <ul class="flex flex-col gap-1 text-sm">
                                <li
                                    v-for="(
                                        play, playIndex
                                    ) in detail.history_plays"
                                    :key="playIndex"
                                    class="flex justify-between gap-2"
                                >
                                    <span>{{ play.session }}</span>
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
                            {{ trans('dj.tracks.show.no_history') }}
                        </p>
                    </UPageCard>
                </div>
            </div>
            <UModal
                v-model:open="splitOpen"
                :title="trans('dj.matching.track.split')"
            >
                <template #body>
                    <p class="text-sm">
                        {{ trans('dj.matching.track.confirm_split') }}
                    </p>
                </template>
                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="splitOpen = false"
                        />
                        <UButton
                            :loading="busy"
                            :label="trans('dj.matching.track.split')"
                            @click="splitOff"
                        />
                    </div>
                </template>
            </UModal>

            <CanonicalMergeModal
                v-if="detail.canonical"
                v-model:open="mergeOpen"
                record="track"
                :source-id="detail.canonical.id"
                :initial-search="detail.canonical.title"
                :title="trans('dj.matching.track.merge_title')"
                :description="trans('dj.matching.track.merge_help')"
                :placeholder="trans('dj.matching.track.merge_search')"
            />
        </template>
    </UDashboardPanel>
</template>

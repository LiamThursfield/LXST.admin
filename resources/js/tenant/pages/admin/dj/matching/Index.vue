<script setup lang="ts">
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { store as merge } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/CanonicalMergeController';
import { store as dismiss } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/MatchDismissalController';
import {
    index,
    rematch,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/MatchingController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type {
    CanonicalMergeData,
    MatchCandidate,
    MatchSuggestion,
} from '@/types';

defineProps<{
    // Deferred, so they're loaded once the page has shown
    trackSuggestions?: MatchSuggestion[];
    artistSuggestions?: MatchSuggestion[];
    needsRematch: boolean;
    unmatchedCount: number;
    merges: {
        data: CanonicalMergeData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    canManage: boolean;
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const rematchOpen = ref(false);
const rematching = ref(false);
const busy = ref<string | null>(null);

function runRematch() {
    router.post(
        rematch.url(),
        {},
        {
            onStart: () => (rematching.value = true),
            onFinish: () => {
                rematching.value = false;
                rematchOpen.value = false;
            },
        },
    );
}

function keep(
    record: 'track' | 'artist',
    target: MatchCandidate,
    source: MatchCandidate,
) {
    busy.value = `${record}-${target.id}-${source.id}`;
    router.post(
        merge.url(),
        { record, target_id: target.id, source_id: source.id },
        { preserveScroll: true, onFinish: () => (busy.value = null) },
    );
}

function notDuplicate(record: 'track' | 'artist', pair: MatchSuggestion) {
    busy.value = `${record}-dismiss-${pair.first.id}-${pair.second.id}`;
    router.post(
        dismiss.url(),
        { record, first_id: pair.first.id, second_id: pair.second.id },
        { preserveScroll: true, onFinish: () => (busy.value = null) },
    );
}

const sections = computed(() => [
    {
        record: 'track' as const,
        title: trans('dj.matching.track_suggestions'),
        key: 'trackSuggestions' as const,
    },
    {
        record: 'artist' as const,
        title: trans('dj.matching.artist_suggestions'),
        key: 'artistSuggestions' as const,
    },
]);

function changeMergesPage(page: number) {
    router.get(
        index.url(),
        { merges_page: page },
        { preserveState: true, preserveScroll: true, only: ['merges'] },
    );
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.matching.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>

                <template #right>
                    <UButton
                        v-if="canManage"
                        icon="i-lucide-refresh-cw"
                        :label="trans('dj.matching.rematch')"
                        @click="rematchOpen = true"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.matching.title')" />

            <div class="flex flex-col gap-8">
                <p class="text-muted max-w-3xl text-sm">
                    {{ trans('dj.matching.description') }}
                </p>

                <UAlert
                    v-if="needsRematch"
                    color="info"
                    variant="subtle"
                    icon="i-lucide-refresh-cw"
                    :description="trans('dj.matching.needs_rematch')"
                />

                <UAlert
                    v-if="unmatchedCount"
                    color="warning"
                    variant="subtle"
                    icon="i-lucide-link-2-off"
                    :description="
                        trans('dj.matching.unmatched', {
                            count: unmatchedCount,
                        })
                    "
                />

                <section
                    v-for="section in sections"
                    :key="section.record"
                    class="flex flex-col gap-3"
                >
                    <h2 class="text-highlighted font-semibold">
                        {{ section.title }}
                    </h2>

                    <Deferred :data="section.key">
                        <template #fallback>
                            <div class="flex flex-col gap-3">
                                <USkeleton class="h-20 w-full" />
                                <USkeleton class="h-20 w-full" />
                            </div>
                        </template>

                        <p
                            v-if="!$props[section.key]?.length"
                            class="text-muted text-sm"
                        >
                            {{ trans('dj.matching.no_suggestions') }}
                        </p>

                        <UCard
                            v-for="pair in $props[section.key]"
                            :key="`${pair.first.id}-${pair.second.id}`"
                            :ui="{ body: 'p-4 sm:p-4' }"
                        >
                            <div
                                class="flex flex-col gap-3 md:flex-row md:items-center"
                            >
                                <div
                                    v-for="(candidate, side) in [
                                        pair.first,
                                        pair.second,
                                    ]"
                                    :key="candidate.id"
                                    class="border-default flex flex-1 items-center justify-between gap-3 rounded-md border p-3"
                                >
                                    <div class="flex min-w-0 flex-col">
                                        <Link
                                            :href="candidate.url"
                                            class="text-highlighted truncate font-medium hover:underline"
                                        >
                                            {{ candidate.name }}
                                        </Link>
                                        <span
                                            class="text-muted truncate text-xs"
                                        >
                                            <template v-if="candidate.detail">
                                                {{ candidate.detail }} ·
                                            </template>
                                            {{
                                                trans('dj.matching.sources', {
                                                    count: candidate.sources_count,
                                                })
                                            }}
                                        </span>
                                        <UBadge
                                            v-if="candidate.is_verified"
                                            color="success"
                                            variant="subtle"
                                            size="sm"
                                            class="mt-1 w-fit"
                                        >
                                            {{ trans('dj.matching.verified') }}
                                        </UBadge>
                                    </div>
                                    <UButton
                                        v-if="canManage"
                                        size="sm"
                                        color="neutral"
                                        variant="outline"
                                        :label="trans('dj.matching.keep')"
                                        :title="trans('dj.matching.keep_help')"
                                        :loading="
                                            busy ===
                                            `${section.record}-${candidate.id}-${(side === 0 ? pair.second : pair.first).id}`
                                        "
                                        @click="
                                            keep(
                                                section.record,
                                                candidate,
                                                side === 0
                                                    ? pair.second
                                                    : pair.first,
                                            )
                                        "
                                    />
                                </div>

                                <div
                                    class="flex shrink-0 items-center justify-between gap-2 md:flex-col md:items-end"
                                >
                                    <UBadge color="neutral" variant="subtle">
                                        {{
                                            trans('dj.matching.similarity', {
                                                percent: Math.round(
                                                    pair.similarity * 100,
                                                ),
                                            })
                                        }}
                                    </UBadge>
                                    <UButton
                                        v-if="canManage"
                                        size="sm"
                                        color="neutral"
                                        variant="ghost"
                                        :label="
                                            trans('dj.matching.not_duplicate')
                                        "
                                        :loading="
                                            busy ===
                                            `${section.record}-dismiss-${pair.first.id}-${pair.second.id}`
                                        "
                                        @click="
                                            notDuplicate(section.record, pair)
                                        "
                                    />
                                </div>
                            </div>
                        </UCard>
                    </Deferred>
                </section>

                <section class="flex flex-col gap-3">
                    <h2 class="text-highlighted font-semibold">
                        {{ trans('dj.matching.history') }}
                    </h2>

                    <UTable
                        :columns="[
                            {
                                accessorKey: 'change',
                                header: trans('dj.matching.columns.change'),
                            },
                            {
                                accessorKey: 'record_label',
                                header: trans('dj.matching.columns.record'),
                            },
                            {
                                accessorKey: 'user',
                                header: trans('dj.matching.columns.by'),
                            },
                            {
                                accessorKey: 'created_at',
                                header: trans('dj.matching.columns.when'),
                            },
                        ]"
                        :data="merges.data"
                        :empty="trans('dj.matching.no_history')"
                    >
                        <template #change-cell="{ row }">
                            <div class="flex flex-col whitespace-normal">
                                <span>
                                    {{
                                        trans(
                                            `dj.matching.history_entry.${row.original.action}`,
                                            {
                                                source: row.original
                                                    .source_name,
                                                target: row.original
                                                    .target_name,
                                            },
                                        )
                                    }}
                                </span>
                                <span class="text-muted text-xs">
                                    {{
                                        trans('dj.matching.moved', {
                                            count: row.original.moved_count,
                                        })
                                    }}
                                </span>
                            </div>
                        </template>

                        <template #user-cell="{ row }">
                            {{
                                row.original.user ??
                                trans('dj.matching.automatic')
                            }}
                        </template>

                        <template #created_at-cell="{ row }">
                            {{
                                formatDateTime(
                                    row.original.created_at,
                                    timezone,
                                )
                            }}
                        </template>
                    </UTable>

                    <div
                        v-if="merges.meta.last_page > 1"
                        class="flex justify-end"
                    >
                        <UPagination
                            :items-per-page="merges.meta.per_page"
                            :page="merges.meta.current_page"
                            :total="merges.meta.total"
                            @update:page="changeMergesPage"
                        />
                    </div>
                </section>
            </div>

            <UModal
                v-model:open="rematchOpen"
                :title="trans('dj.matching.rematch')"
                :description="trans('dj.matching.rematch_help')"
            >
                <template #body>
                    <p class="text-sm">
                        {{ trans('dj.matching.confirm_rematch') }}
                    </p>
                </template>
                <template #footer>
                    <div class="flex w-full justify-end gap-2">
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('common.actions.cancel')"
                            @click="rematchOpen = false"
                        />
                        <UButton
                            :loading="rematching"
                            :label="trans('dj.matching.rematch')"
                            @click="runRematch"
                        />
                    </div>
                </template>
            </UModal>
        </template>
    </UDashboardPanel>
</template>

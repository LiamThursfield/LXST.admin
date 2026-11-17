<script setup lang="ts">
// Imported explicitly, as the shared list components do.
import UEmpty from '@nuxt/ui/components/Empty.vue';
import { index as apiTokens } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/ApiTokenController';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/SiteSettings/McpActivityController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import SiteSettingsSubLayout from '@/sublayouts/admin/SiteSettingsSubLayout.vue';
import type { McpAuditLogData } from '@/types';

const props = defineProps<{
    filter?: {
        search?: string;
        token?: string;
        outcome?: string;
    };
    columns: Array<{
        accessorKey: string;
        header: string;
    }>;
    entries: {
        data: McpAuditLogData[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            per_page: number;
        };
    };
    tokens: Array<{ label: string; value: string }>;
    outcomes: Array<{ label: string; value: string }>;
    retentionDays: number;
}>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);

const { filters, hasFilters, goToPage } = useListFilters({
    url: () => index.url(),
    initial: {
        search: props.filter?.search ?? '',
        token: props.filter?.token ?? 'any',
        outcome: props.filter?.outcome ?? 'any',
    },
    blanks: { token: 'any', outcome: 'any' },
});

const tokenItems = computed(() => [
    { label: trans('mcp.activity.all_tokens'), value: 'any' },
    ...props.tokens,
]);

const outcomeItems = computed(() => [
    { label: trans('mcp.activity.all_outcomes'), value: 'any' },
    ...props.outcomes,
]);

const outcomeColours: Record<
    string,
    'success' | 'warning' | 'error' | 'neutral'
> = {
    success: 'success',
    denied: 'warning',
    invalid: 'warning',
    rate_limited: 'warning',
    error: 'error',
};

const pageRange = computed(() => {
    const { current_page, per_page, total } = props.entries.meta;

    return {
        from: total ? (current_page - 1) * per_page + 1 : 0,
        to: Math.min(current_page * per_page, total),
        total,
    };
});

// The entry whose message, changes and arguments are shown
const selected = ref<McpAuditLogData | null>(null);
const detailsOpen = computed({
    get: () => selected.value !== null,
    set: (open: boolean) => {
        if (!open) {
            selected.value = null;
        }
    },
});

function pretty(value: unknown): string {
    return JSON.stringify(value, null, 2);
}
</script>

<template>
    <SiteSettingsSubLayout>
        <SettingsPage
            :title="trans('mcp.activity.title')"
            :description="
                trans('mcp.activity.description', { days: retentionDays })
            "
        >
            <template #actions>
                <UButton
                    as="Link"
                    :href="apiTokens.url()"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-arrow-left"
                    :label="trans('site_settings.api_tokens.title')"
                />
            </template>

            <div class="border-default flex flex-col gap-4 border-t pt-6">
                <div class="flex flex-col gap-2 sm:flex-row">
                    <UInput
                        v-model="filters.search"
                        class="w-full sm:w-64"
                        icon="i-lucide-search"
                        :placeholder="trans('mcp.activity.search')"
                        :aria-label="trans('mcp.activity.search')"
                    />
                    <USelect
                        v-model="filters.token"
                        class="w-full sm:w-52"
                        :items="tokenItems"
                        :aria-label="trans('mcp.activity.columns.token')"
                    />
                    <USelect
                        v-model="filters.outcome"
                        class="w-full sm:w-44"
                        :items="outcomeItems"
                        :aria-label="trans('mcp.activity.columns.outcome')"
                    />
                </div>

                <UCard variant="outline" :ui="{ body: 'p-0 sm:p-0' }">
                    <UEmpty
                        v-if="!entries.data.length"
                        :icon="
                            hasFilters
                                ? 'i-lucide-search-x'
                                : 'i-lucide-scroll-text'
                        "
                        :title="
                            hasFilters
                                ? trans('common.list.no_matches')
                                : trans('mcp.activity.empty')
                        "
                        :description="
                            hasFilters
                                ? trans('common.list.no_matches_description')
                                : undefined
                        "
                        variant="naked"
                        class="py-12"
                    />

                    <UTable
                        v-else
                        :columns="columns"
                        :data="entries.data"
                        class="w-full"
                    >
                        <template #created_at-cell="{ row }">
                            {{
                                formatDateTime(
                                    row.original.created_at,
                                    timezone,
                                )
                            }}
                        </template>

                        <template #token-cell="{ row }">
                            <div class="flex flex-col">
                                <span class="text-highlighted">
                                    {{ row.original.token }}
                                </span>
                                <span
                                    v-if="row.original.user"
                                    class="text-muted text-xs"
                                >
                                    {{ row.original.user }}
                                </span>
                            </div>
                        </template>

                        <template #tool-cell="{ row }">
                            <div class="flex flex-col">
                                <code class="text-sm">{{
                                    row.original.tool
                                }}</code>
                                <span
                                    v-if="row.original.target"
                                    class="text-muted text-xs"
                                >
                                    {{ row.original.target }}
                                </span>
                            </div>
                        </template>

                        <template #outcome-cell="{ row }">
                            <UBadge
                                :color="
                                    outcomeColours[
                                        row.original.outcome.value
                                    ] ?? 'neutral'
                                "
                                variant="subtle"
                            >
                                {{ row.original.outcome.label }}
                            </UBadge>
                        </template>

                        <template #details-cell="{ row }">
                            <UButton
                                color="neutral"
                                variant="ghost"
                                icon="i-lucide-info"
                                :aria-label="trans('mcp.activity.details')"
                                @click="selected = row.original"
                            />
                        </template>
                    </UTable>
                </UCard>

                <div
                    v-if="entries.meta.last_page > 1"
                    class="flex flex-wrap items-center justify-between gap-2 text-sm"
                >
                    <span class="text-muted">
                        {{ trans('common.list.showing', pageRange) }}
                    </span>
                    <div class="flex gap-2">
                        <UButton
                            color="neutral"
                            variant="outline"
                            icon="i-lucide-chevron-left"
                            :disabled="entries.meta.current_page <= 1"
                            :label="trans('common.list.previous')"
                            @click="goToPage(entries.meta.current_page - 1)"
                        />
                        <UButton
                            color="neutral"
                            variant="outline"
                            trailing-icon="i-lucide-chevron-right"
                            :disabled="
                                entries.meta.current_page >=
                                entries.meta.last_page
                            "
                            :label="trans('common.list.next')"
                            @click="goToPage(entries.meta.current_page + 1)"
                        />
                    </div>
                </div>
            </div>
        </SettingsPage>

        <UModal
            v-model:open="detailsOpen"
            :title="selected?.tool ?? ''"
            :description="selected?.target ?? undefined"
        >
            <template #body>
                <div v-if="selected" class="flex flex-col gap-4 text-sm">
                    <p v-if="selected.message">{{ selected.message }}</p>
                    <div v-if="selected.changes">
                        <p class="text-highlighted mb-1 font-medium">
                            {{ trans('mcp.activity.changes') }}
                        </p>
                        <pre
                            class="bg-elevated whitespace-pre-wrap break-all rounded-md p-3 text-xs"
                            >{{ pretty(selected.changes) }}</pre>
                    </div>
                    <div>
                        <p class="text-highlighted mb-1 font-medium">
                            {{ trans('mcp.activity.arguments') }}
                        </p>
                        <pre
                            class="bg-elevated whitespace-pre-wrap break-all rounded-md p-3 text-xs"
                            >{{ pretty(selected.arguments) }}</pre>
                    </div>
                </div>
            </template>
        </UModal>
    </SiteSettingsSubLayout>
</template>

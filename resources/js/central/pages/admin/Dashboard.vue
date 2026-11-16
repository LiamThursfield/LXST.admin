<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    index,
    show,
} from '@/actions/App/Http/Controllers/Central/Admin/TenantController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { CentralDashboardData } from '@/types';

type Issue = CentralDashboardData['attention'][number]['issues'][number];

const props = defineProps<CentralDashboardData>();

const { trans } = useTrans();
const timezone = userTimezone(useAuth().value.user);
const numberFormat = new Intl.NumberFormat();

const tiles = computed(() => [
    {
        label: trans('dashboard.stats.active'),
        value: props.stats.active,
        icon: 'i-lucide-building-2',
    },
    {
        label: trans('dashboard.stats.new'),
        value: props.stats.new,
        icon: 'i-lucide-sparkles',
        help: trans('dashboard.stats.new_help', {
            days: props.stats.new_days,
        }),
    },
    {
        label: trans('dashboard.stats.disabled'),
        value: props.stats.disabled,
        icon: 'i-lucide-power-off',
    },
    {
        label: trans('dashboard.stats.failed_jobs'),
        value: props.queue.failed,
        icon: 'i-lucide-triangle-alert',
        alert: props.queue.failed > 0,
        help: trans('dashboard.stats.pending_jobs', {
            count: numberFormat.format(props.queue.pending),
        }),
    },
]);

const issueColors: Record<Issue, 'error' | 'warning' | 'neutral'> = {
    stripe: 'error',
    no_fee: 'warning',
    no_own_domain: 'neutral',
    disabled: 'warning',
};

function issueLabel(
    issue: Issue,
    item: CentralDashboardData['attention'][number],
): string {
    return issue === 'disabled' && item.disabled_at
        ? trans('dashboard.issues.disabled', {
              date: formatDateTime(item.disabled_at, timezone),
          })
        : trans(`dashboard.issues.${issue}`);
}

/** A bar's width, as a share of the active tenants */
function barWidth(count: number): string {
    return `${props.stats.active === 0 ? 0 : (count / props.stats.active) * 100}%`;
}
</script>

<template>
    <UDashboardPanel id="home">
        <template #header>
            <UDashboardNavbar
                :title="trans('common.dashboard.title')"
                :ui="{ right: 'gap-3' }"
            >
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('common.dashboard.title')" />

            <div class="flex flex-col gap-6">
                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <UCard v-for="tile in tiles" :key="tile.label">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-muted text-sm">
                                    {{ tile.label }}
                                </p>
                                <p
                                    class="text-2xl font-semibold tabular-nums"
                                    :class="
                                        tile.alert
                                            ? 'text-error'
                                            : 'text-highlighted'
                                    "
                                >
                                    {{ numberFormat.format(tile.value) }}
                                </p>
                                <p
                                    v-if="tile.help"
                                    class="text-dimmed mt-1 text-xs"
                                >
                                    {{ tile.help }}
                                </p>
                            </div>
                            <UIcon
                                :name="tile.icon"
                                class="size-5 shrink-0"
                                :class="
                                    tile.alert ? 'text-error' : 'text-dimmed'
                                "
                            />
                        </div>
                    </UCard>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <UCard class="lg:col-span-2" :ui="{ body: 'p-0 sm:p-0' }">
                        <template #header>
                            <h3 class="font-semibold">
                                {{ trans('dashboard.attention.title') }}
                            </h3>
                        </template>
                        <ul
                            v-if="attention.length"
                            class="divide-default divide-y"
                        >
                            <li
                                v-for="item in attention"
                                :key="item.id"
                                class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <Link
                                    :href="show.url(item.id)"
                                    class="text-highlighted break-all font-medium hover:underline sm:shrink-0"
                                >
                                    {{ item.name }}
                                </Link>
                                <div
                                    class="flex min-w-0 flex-wrap gap-1.5 sm:justify-end"
                                >
                                    <UBadge
                                        v-for="issue in item.issues"
                                        :key="issue"
                                        :color="issueColors[issue]"
                                        variant="subtle"
                                    >
                                        {{ issueLabel(issue, item) }}
                                    </UBadge>
                                </div>
                            </li>
                        </ul>
                        <p
                            v-else
                            class="text-muted flex items-center gap-2 p-4 text-sm"
                        >
                            <UIcon
                                name="i-lucide-circle-check"
                                class="text-success size-4"
                            />
                            {{ trans('dashboard.attention.none') }}
                        </p>
                    </UCard>

                    <UCard :ui="{ body: 'p-0 sm:p-0' }">
                        <template #header>
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <h3 class="font-semibold">
                                    {{ trans('dashboard.recent.title') }}
                                </h3>
                                <UButton
                                    as="Link"
                                    :href="index.url()"
                                    size="xs"
                                    color="neutral"
                                    variant="ghost"
                                    trailing-icon="i-lucide-arrow-right"
                                    :label="trans('dashboard.recent.all')"
                                />
                            </div>
                        </template>
                        <ul
                            v-if="recent.length"
                            class="divide-default divide-y"
                        >
                            <li
                                v-for="tenant in recent"
                                :key="tenant.id"
                                class="px-4 py-2 text-sm"
                            >
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="show.url(tenant.id)"
                                        class="text-highlighted truncate font-medium hover:underline"
                                    >
                                        {{ tenant.name }}
                                    </Link>
                                    <UBadge
                                        v-if="tenant.is_disabled"
                                        color="warning"
                                        variant="subtle"
                                        size="sm"
                                    >
                                        {{ trans('tenants.status.disabled') }}
                                    </UBadge>
                                </div>
                                <p class="text-muted truncate text-xs">
                                    {{ tenant.industry }} ·
                                    {{
                                        formatDateTime(
                                            tenant.created_at,
                                            timezone,
                                        )
                                    }}
                                </p>
                            </li>
                        </ul>
                        <p v-else class="text-muted p-4 text-sm">
                            {{ trans('dashboard.recent.none') }}
                        </p>
                    </UCard>
                </div>

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <UCard>
                        <template #header>
                            <h3 class="font-semibold">
                                {{ trans('dashboard.modules.title') }}
                            </h3>
                            <p class="text-muted text-sm">
                                {{ trans('dashboard.modules.help') }}
                            </p>
                        </template>
                        <ul class="flex flex-col gap-3">
                            <li
                                v-for="module in modules"
                                :key="module.value"
                                class="text-sm"
                            >
                                <div class="mb-1 flex justify-between gap-4">
                                    <span>{{ module.label }}</span>
                                    <span class="tabular-nums">{{
                                        numberFormat.format(module.count)
                                    }}</span>
                                </div>
                                <div class="bg-elevated h-1.5 rounded-full">
                                    <div
                                        class="bg-primary h-full rounded-full"
                                        :style="{
                                            width: barWidth(module.count),
                                        }"
                                    />
                                </div>
                            </li>
                        </ul>
                    </UCard>

                    <UCard>
                        <template #header>
                            <h3 class="font-semibold">
                                {{ trans('dashboard.industries.title') }}
                            </h3>
                            <p class="text-muted text-sm">
                                {{ trans('dashboard.industries.help') }}
                            </p>
                        </template>
                        <ul
                            v-if="industries.length"
                            class="flex flex-col gap-3"
                        >
                            <li
                                v-for="industry in industries"
                                :key="industry.value"
                                class="text-sm"
                            >
                                <div class="mb-1 flex justify-between gap-4">
                                    <span>{{ industry.label }}</span>
                                    <span class="tabular-nums">{{
                                        numberFormat.format(industry.count)
                                    }}</span>
                                </div>
                                <div class="bg-elevated h-1.5 rounded-full">
                                    <div
                                        class="bg-primary h-full rounded-full"
                                        :style="{
                                            width: barWidth(industry.count),
                                        }"
                                    />
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-muted text-sm">
                            {{ trans('dashboard.industries.none') }}
                        </p>
                    </UCard>
                </div>
            </div>
        </template>
    </UDashboardPanel>
</template>

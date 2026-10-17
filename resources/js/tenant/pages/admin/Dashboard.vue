<script setup lang="ts">
import { Deferred, Head } from '@inertiajs/vue3';
import DashboardController from '@/actions/App/Http/Controllers/Tenant/Admin/DashboardController';
import type { PageViewAnalyticsData, SelectItem } from '@/types';
import PageViewAnalytics from './components/PageViewAnalytics.vue';

const props = defineProps<{
    /** Null without the CMS module or permission; loaded after the page otherwise */
    analytics?: PageViewAnalyticsData | null;
    analyticsRanges: SelectItem[];
}>();

const { trans } = useTrans();

const range = ref<string>(
    new URLSearchParams(window.location.search).get('range') ?? '30d',
);
const showAnalytics = computed(() => props.analyticsRanges.length > 0);

watch(range, (value) => {
    router.get(
        DashboardController.url(),
        value === '30d' ? {} : { range: value },
        {
            only: ['analytics'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
});
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

        <template v-if="showAnalytics" #body>
            <Head :title="trans('common.dashboard.title')" />
            <section class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <h2 class="text-highlighted text-lg font-semibold">
                        {{ trans('cms.analytics.title') }}
                    </h2>
                    <USelect
                        v-model="range"
                        :items="analyticsRanges"
                        :aria-label="trans('cms.analytics.range')"
                        class="w-44"
                    />
                </div>

                <Deferred data="analytics">
                    <template #fallback>
                        <div class="flex flex-col gap-6">
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <USkeleton class="h-24" />
                                <USkeleton class="h-24" />
                            </div>
                            <USkeleton class="h-72" />
                            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <USkeleton class="h-64" />
                                <USkeleton class="h-64" />
                            </div>
                        </div>
                    </template>

                    <PageViewAnalytics
                        v-if="analytics"
                        :analytics="analytics"
                    />
                </Deferred>
            </section>
        </template>
    </UDashboardPanel>
</template>

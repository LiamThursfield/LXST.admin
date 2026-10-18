<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    booth,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/RequestQueueController';
import RequestCard from '@/tenant/components/dj/RequestCard.vue';
import RequestModeControl from '@/tenant/components/dj/RequestModeControl.vue';
import { useRequestQueue } from '@/tenant/composables/dj/useRequestQueue';
import type {
    RequestQueueData,
    RequestQueueGig,
    RequestStatus,
    SelectItem,
} from '@/types';

const props = defineProps<{
    gig: RequestQueueGig | null;
    requests: RequestQueueData | null;
    gigs: SelectItem[];
}>();

const { trans } = useTrans();
const { busy, setStatus, setMode } = useRequestQueue(() => props.gig);

const gigId = ref(props.gig?.id);

watch(gigId, (id) =>
    router.get(index.url(), id ? { gig: id } : {}, { preserveScroll: true }),
);

const main: RequestStatus[] = ['pending', 'accepted'];
const side: RequestStatus[] = ['played', 'declined'];
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.queue.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
                <template #right>
                    <UButton
                        v-if="gig"
                        as="Link"
                        :href="booth.url({ query: { gig: gig.id } })"
                        icon="i-lucide-maximize"
                        :label="trans('dj.queue.booth')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.queue.title')" />

            <div class="flex flex-col gap-6">
                <p class="text-muted text-sm">
                    {{ trans('dj.queue.description') }}
                </p>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <USelectMenu
                        v-model="gigId"
                        :items="gigs"
                        value-key="value"
                        class="w-full sm:w-80"
                        :placeholder="trans('dj.queue.gig')"
                    />
                    <div v-if="gig" class="flex flex-wrap items-center gap-2">
                        <RequestModeControl :gig="gig" @mode="setMode" />
                        <UButton
                            as="Link"
                            :href="gig.edit_url"
                            size="sm"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-calendar-days"
                            :label="trans('dj.queue.gig_page')"
                        />
                    </div>
                </div>

                <p v-if="!gig || !requests" class="text-muted text-sm">
                    {{ trans('dj.queue.no_gig') }}
                </p>

                <div v-else class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="flex flex-col gap-6 lg:col-span-2">
                        <section
                            v-for="status in main"
                            :key="status"
                            class="flex flex-col gap-3"
                        >
                            <h2 class="text-highlighted font-semibold">
                                {{ trans(`dj.queue.sections.${status}`) }}
                                <span class="text-muted font-normal">
                                    {{ requests[status].length }}
                                </span>
                            </h2>
                            <RequestCard
                                v-for="item in requests[status]"
                                :key="item.id"
                                :item="item"
                                :timezone="gig.timezone"
                                :busy="busy.includes(item.id)"
                                @status="(next) => setStatus(item, next)"
                            />
                            <p
                                v-if="!requests[status].length"
                                class="text-muted text-sm"
                            >
                                {{ trans('dj.queue.empty') }}
                            </p>
                        </section>
                    </div>

                    <div class="flex flex-col gap-6">
                        <section
                            v-for="status in side"
                            :key="status"
                            class="flex flex-col gap-3"
                        >
                            <h2 class="text-highlighted font-semibold">
                                {{ trans(`dj.queue.sections.${status}`) }}
                                <span class="text-muted font-normal">
                                    {{ requests[status].length }}
                                </span>
                            </h2>
                            <RequestCard
                                v-for="item in requests[status]"
                                :key="item.id"
                                :item="item"
                                :timezone="gig.timezone"
                                :busy="busy.includes(item.id)"
                                @status="(next) => setStatus(item, next)"
                            />
                            <p
                                v-if="!requests[status].length"
                                class="text-muted text-sm"
                            >
                                {{ trans('dj.queue.empty') }}
                            </p>
                        </section>
                    </div>
                </div>
            </div>
        </template>
    </UDashboardPanel>
</template>

<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigController';
import {
    destroy as unpin,
    store as pin,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigPinController';
import { show as qrCode } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigQrCodeController';
import { store as storeSetlist } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SetlistController';
import GigForm from '@/tenant/pages/admin/dj/gig/components/GigForm.vue';
import type { GigData, RequestDefaults, SelectItem } from '@/types';

const props = defineProps<{
    gig: { data: GigData };
    overlaps: Array<{ id: number; name: string; url: string }>;
    defaults: RequestDefaults;
    requestsModes: SelectItem[];
    setlists: Array<{
        id: number;
        name: string;
        items_count: number;
        url: string;
    }>;
    canCreateSetlist: boolean;
}>();

const { trans } = useTrans();
const toast = useToast();
const gig = computed(() => props.gig.data);

function togglePin() {
    router.visit((gig.value.is_pinned ? unpin : pin).url(gig.value.id), {
        method: gig.value.is_pinned ? 'delete' : 'post',
        preserveScroll: true,
    });
}

async function copyLink() {
    if (!gig.value.request_url) {
        return;
    }

    await navigator.clipboard.writeText(gig.value.request_url);
    toast.add({
        title: trans('dj.gigs.share.copied'),
        icon: 'i-lucide-check',
        color: 'success',
    });
}

const linksToThisGig = computed(
    () => gig.value.request_url?.includes(String(gig.value.id)) ?? false,
);

function newSetlist() {
    router.post(storeSetlist.url(), {
        name: gig.value.name,
        gig_id: gig.value.id,
        visibility: 'private',
    });
}
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.gigs.edit')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
                <template #right>
                    <UButton
                        color="neutral"
                        variant="outline"
                        :icon="
                            gig.is_pinned ? 'i-lucide-pin-off' : 'i-lucide-pin'
                        "
                        :label="
                            gig.is_pinned
                                ? trans('dj.gigs.unpin')
                                : trans('dj.gigs.pin')
                        "
                        @click="togglePin"
                    />
                    <UButton
                        :as="Link"
                        :href="index.url()"
                        color="neutral"
                        variant="ghost"
                        icon="i-lucide-arrow-left"
                        :label="trans('dj.gigs.title')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="gig.name" />
            <div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
                <div class="flex flex-wrap gap-2">
                    <UBadge
                        v-if="gig.is_current"
                        color="primary"
                        variant="subtle"
                        >{{ trans('dj.gigs.current') }}</UBadge
                    >
                    <UBadge
                        v-if="gig.is_pinned"
                        color="primary"
                        variant="outline"
                        >{{ trans('dj.gigs.pinned') }}</UBadge
                    >
                    <UBadge
                        :color="gig.requests_open ? 'success' : 'neutral'"
                        variant="subtle"
                    >
                        {{
                            gig.requests_open
                                ? trans('dj.gigs.requests_open')
                                : trans('dj.gigs.requests_closed')
                        }}
                    </UBadge>
                </div>

                <UAlert
                    v-if="overlaps.length"
                    color="warning"
                    variant="subtle"
                    icon="i-lucide-calendar-range"
                    :description="
                        trans('dj.gigs.overlaps', {
                            names: overlaps
                                .map((other) => other.name)
                                .join(', '),
                        })
                    "
                />

                <UPageCard
                    :title="trans('dj.gigs.sections.share')"
                    variant="subtle"
                >
                    <template v-if="gig.request_url">
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-start"
                        >
                            <img
                                :src="qrCode.url(gig.id)"
                                :alt="trans('dj.gigs.share.qr')"
                                class="size-36 shrink-0 rounded-md bg-white p-1"
                            />
                            <div class="flex min-w-0 flex-1 flex-col gap-2">
                                <div class="flex gap-2">
                                    <UInput
                                        :model-value="gig.request_url"
                                        readonly
                                        class="w-full font-mono"
                                        :aria-label="
                                            trans('dj.gigs.share.link')
                                        "
                                    />
                                    <UButton
                                        icon="i-lucide-copy"
                                        color="neutral"
                                        variant="outline"
                                        :label="trans('dj.gigs.share.copy')"
                                        @click="copyLink"
                                    />
                                </div>
                                <UButton
                                    :href="
                                        qrCode.url(gig.id, {
                                            query: { download: '1' },
                                        })
                                    "
                                    variant="link"
                                    icon="i-lucide-download"
                                    class="w-fit px-0"
                                    :label="trans('dj.gigs.share.download_qr')"
                                />
                                <p
                                    v-if="!linksToThisGig"
                                    class="text-muted text-sm"
                                >
                                    {{ trans('dj.gigs.share.current_only') }}
                                </p>
                            </div>
                        </div>
                    </template>
                    <p v-else class="text-muted text-sm">
                        {{ trans('dj.gigs.share.no_url') }}
                    </p>
                </UPageCard>

                <UPageCard
                    :title="trans('dj.gigs.sections.setlists')"
                    variant="subtle"
                >
                    <ul
                        v-if="setlists.length"
                        class="flex flex-col gap-1 text-sm"
                    >
                        <li
                            v-for="setlist in setlists"
                            :key="setlist.id"
                            class="flex justify-between gap-2"
                        >
                            <Link :href="setlist.url" class="hover:underline">{{
                                setlist.name
                            }}</Link>
                            <span class="text-muted text-xs">{{
                                trans('dj.songs.total', {
                                    count: setlist.items_count,
                                })
                            }}</span>
                        </li>
                    </ul>
                    <p v-else class="text-muted text-sm">
                        {{ trans('dj.gigs.sections.no_setlists') }}
                    </p>
                    <UButton
                        v-if="canCreateSetlist"
                        icon="i-lucide-list-plus"
                        color="neutral"
                        variant="outline"
                        class="w-fit"
                        :label="trans('dj.gigs.sections.new_setlist')"
                        @click="newSetlist"
                    />
                </UPageCard>

                <GigForm
                    :gig="gig"
                    :defaults="defaults"
                    :requests-modes="requestsModes"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>

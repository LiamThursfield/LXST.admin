<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    destroy as unpin,
    store as pin,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigPinController';
import { show as qrCode } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigQrCodeController';
import { show as stats } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigStatsController';
import { storeForGig as storeGuestPass } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GuestPassController';
import { store as storeSetlist } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/SetlistController';
import type { EditorTab } from '@/components/EditorLayout.vue';
import GuestPassesCard from '@/tenant/components/dj/GuestPassesCard.vue';
import GigForm from '@/tenant/pages/admin/dj/gig/components/GigForm.vue';
import GigPlayed from '@/tenant/pages/admin/dj/gig/components/GigPlayed.vue';
import type {
    GigData,
    GuestPassData,
    HistorySessionData,
    PlayedTrackData,
    RequestDefaults,
    SelectItem,
} from '@/types';

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
    played: { data: PlayedTrackData[] };
    linkedSessions: { data: HistorySessionData[] };
    requestPasses?: { data: GuestPassData[] } | null;
    canViewStats: boolean;
}>();

const { trans } = useTrans();
const toast = useToast();
const gig = computed(() => props.gig.data);

const pinLabel = computed(() =>
    gig.value.is_pinned ? trans('dj.gigs.unpin') : trans('dj.gigs.pin'),
);

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
    () => gig.value.request_url?.includes(gig.value.public_id) ?? false,
);

const extraTabs = computed<EditorTab[]>(() => [
    { value: 'sharing', label: trans('dj.gigs.sections.sharing') },
    {
        value: 'setlists',
        label: trans('dj.gigs.sections.setlists'),
        count: props.setlists.length,
    },
    { value: 'played', label: trans('dj.gigs.sections.played') },
]);

function newSetlist() {
    router.post(storeSetlist.url(), {
        name: gig.value.name,
        gig_id: gig.value.id,
        visibility: 'private',
    });
}
</script>

<template>
    <GigForm
        :gig="gig"
        :defaults="defaults"
        :requests-modes="requestsModes"
        :extra-tabs="extraTabs"
    >
        <template #badges>
            <UBadge v-if="gig.is_current" color="primary" variant="subtle">{{
                trans('dj.gigs.current')
            }}</UBadge>
            <UBadge v-if="gig.is_pinned" color="primary" variant="outline">{{
                trans('dj.gigs.pinned')
            }}</UBadge>
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
        </template>

        <template #actions>
            <UButton
                v-if="canViewStats"
                as="Link"
                :href="stats.url(gig.id)"
                color="neutral"
                variant="ghost"
                icon="i-lucide-chart-line"
                :label="trans('dj.stats.title')"
                :aria-label="trans('dj.stats.title')"
                :ui="{ label: 'hidden sm:inline' }"
            />
            <UButton
                color="neutral"
                variant="outline"
                :icon="gig.is_pinned ? 'i-lucide-pin-off' : 'i-lucide-pin'"
                :label="pinLabel"
                :aria-label="pinLabel"
                :ui="{ label: 'hidden sm:inline' }"
                @click="togglePin"
            />
        </template>

        <template v-if="overlaps.length" #notice>
            <UAlert
                v-if="overlaps.length"
                color="warning"
                variant="subtle"
                icon="i-lucide-calendar-range"
                :description="
                    trans('dj.gigs.overlaps', {
                        names: overlaps.map((other) => other.name).join(', '),
                    })
                "
            />
        </template>

        <template #tab-sharing>
            <UPageCard
                :title="trans('dj.gigs.sections.share')"
                variant="subtle"
            >
                <template v-if="gig.request_url">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
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
                                    :aria-label="trans('dj.gigs.share.link')"
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

            <GuestPassesCard
                v-if="requestPasses"
                :passes="requestPasses.data"
                :title="trans('dj.guest_passes.requests_title')"
                :description="trans('dj.guest_passes.requests_help')"
                :store-url="storeGuestPass.url(gig.id)"
            />
        </template>

        <template #tab-setlists>
            <UPageCard
                :title="trans('dj.gigs.sections.setlists')"
                variant="subtle"
            >
                <ul v-if="setlists.length" class="flex flex-col gap-1 text-sm">
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
        </template>

        <template #tab-played>
            <GigPlayed
                :gig="gig"
                :played="played.data"
                :sessions="linkedSessions.data"
            />
        </template>
    </GigForm>
</template>

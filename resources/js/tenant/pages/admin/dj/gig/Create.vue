<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { index } from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GigController';
import GigForm from '@/tenant/pages/admin/dj/gig/components/GigForm.vue';
import type { RequestDefaults, SelectItem } from '@/types';

defineProps<{
    defaultTimezone: string;
    defaults: RequestDefaults;
    requestsModes: SelectItem[];
}>();

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('dj.gigs.create')">
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
                        :label="trans('dj.gigs.title')"
                    />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('dj.gigs.create')" />
            <div class="mx-auto w-full max-w-3xl">
                <GigForm
                    :default-timezone="defaultTimezone"
                    :defaults="defaults"
                    :requests-modes="requestsModes"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>

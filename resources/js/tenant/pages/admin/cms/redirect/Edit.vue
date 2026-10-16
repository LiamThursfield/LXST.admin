<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { RedirectData } from '@/types';
import RedirectForm from './components/RedirectForm.vue';

defineProps<{
    redirect: { data: RedirectData };
}>();

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('cms.redirects.edit.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('cms.redirects.edit.title')" />
            <div class="w-full max-w-4xl">
                <RedirectForm :redirect="redirect.data" />
            </div>
        </template>
    </UDashboardPanel>
</template>

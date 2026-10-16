<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import RedirectForm from './components/RedirectForm.vue';

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('cms.redirects.create.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('cms.redirects.create.title')" />
            <div class="w-full max-w-4xl">
                <RedirectForm />
            </div>
        </template>
    </UDashboardPanel>
</template>

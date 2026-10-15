<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ContactForm from './components/ContactForm.vue';

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('crm.contacts.create.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('crm.contacts.create.title')" />
            <div class="w-full max-w-4xl">
                <ContactForm :create="true" />
            </div>
        </template>
    </UDashboardPanel>
</template>

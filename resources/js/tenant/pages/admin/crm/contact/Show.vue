<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { ContactData } from '@/types';
import ContactForm from './components/ContactForm.vue';

const props = defineProps<{
    contact: { data: ContactData };
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('crm.contacts.show.title', {
        name: props.contact.data.name_with_title || props.contact.data.email,
    }),
);
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="title">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="title" />
            <div class="w-full max-w-4xl">
                <ContactForm :contact="contact.data" :readonly="true" />
            </div>
        </template>
    </UDashboardPanel>
</template>

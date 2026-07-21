<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserForm from './components/UserForm.vue';

defineProps<{
    roles: Array<{ label: string; value: string }>;
}>();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar title="Create User">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head title="Create User" />
            <div class="w-full max-w-4xl">
                <UserForm :roles="roles" :create="true" />
            </div>
        </template>
    </UDashboardPanel>
</template>

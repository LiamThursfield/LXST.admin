<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserForm from './components/UserForm.vue';

defineProps<{
    user: {
        data: {
            id: number;
            first_name: string;
            last_name: string;
            email: string;
            roles?: string[];
        };
    };
    roles: Array<{ label: string; value: string }>;
}>();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar
                :title="`User: ${user.data.first_name} ${user.data.last_name}`"
            >
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head
                :title="`User: ${user.data.first_name} ${user.data.last_name}`"
            />
            <div class="w-full max-w-4xl">
                <UserForm :user="user.data" :roles="roles" :readonly="true" />
            </div>
        </template>
    </UDashboardPanel>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import UserForm from './components/UserForm.vue';

const { trans } = useTrans();

defineProps<{
    roles: Array<{ label: string; value: string }>;
}>();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar :title="trans('users.create.title')">
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('users.create.title')" />
            <div class="w-full max-w-4xl">
                <UserForm :roles="roles" :create="true" />
            </div>
        </template>
    </UDashboardPanel>
</template>

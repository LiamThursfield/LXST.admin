<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { CentralUserData } from '@/types';
import UserForm from './components/UserForm.vue';

const props = defineProps<{
    user: { data: CentralUserData };
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('users.edit.title', {
        name: `${props.user.data.first_name} ${props.user.data.last_name}`,
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
            <div class="w-full max-w-3xl">
                <UserForm :user="user.data" />
            </div>
        </template>
    </UDashboardPanel>
</template>

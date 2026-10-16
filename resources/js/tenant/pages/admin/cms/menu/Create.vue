<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { MenuPageOption } from '@/types';
import MenuEditor from './components/MenuEditor.vue';

defineProps<{
    pages: MenuPageOption[];
}>();

const { trans } = useTrans();

const title = computed(() => trans('cms.menus.create.title'));
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
                <MenuEditor :pages="pages" />
            </div>
        </template>
    </UDashboardPanel>
</template>

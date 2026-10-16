<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { MenuData, MenuPageOption } from '@/types';
import MenuEditor from './components/MenuEditor.vue';

const props = defineProps<{
    menu: { data: MenuData };
    pages: MenuPageOption[];
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('cms.menus.edit.title', { name: props.menu.data.name }),
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
                <MenuEditor :pages="pages" :menu="menu.data" />
            </div>
        </template>
    </UDashboardPanel>
</template>

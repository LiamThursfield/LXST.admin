<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { PageData, TemplateData } from '@/types';
import PageEditor from './components/PageEditor.vue';

const props = defineProps<{
    page: { data: PageData };
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    menuOptions: Array<{ value: number; label: string }>;
    layouts: Array<{ value: number; label: string }>;
    parents: Array<{ value: number; label: string }>;
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('cms.pages.show.title', { name: props.page.data.name }),
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
                <PageEditor
                    :templates="templates.data"
                    :nested-templates="nestedTemplates.data"
                    :menus="menuOptions"
                    :layouts="layouts"
                    :parents="parents"
                    :page="page.data"
                    readonly
                />
            </div>
        </template>
    </UDashboardPanel>
</template>

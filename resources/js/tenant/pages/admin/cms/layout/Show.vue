<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { LayoutData, TemplateData } from '@/types';
import LayoutEditor from './components/LayoutEditor.vue';

const props = defineProps<{
    layout: { data: LayoutData };
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
}>();

const { trans } = useTrans();

const title = computed(() =>
    trans('cms.layouts.show.title', { name: props.layout.data.name }),
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
                <LayoutEditor
                    :templates="templates.data"
                    :nested-templates="nestedTemplates.data"
                    :layout="layout.data"
                    readonly
                />
            </div>
        </template>
    </UDashboardPanel>
</template>

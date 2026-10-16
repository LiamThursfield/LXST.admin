<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { CrmOptions } from '@/tenant/components/cms/nestedTemplates';
import type { TemplateData } from '@/types';
import PageEditor from './components/PageEditor.vue';

defineProps<{
    defaultParentId: number | null;
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    menuOptions: Array<{ value: number; label: string }>;
    crmOptions: CrmOptions | null;
    layouts: Array<{ value: number; label: string }>;
    parents: Array<{ value: number; label: string }>;
}>();

const { trans } = useTrans();

const title = computed(() => trans('cms.pages.create.title'));
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
                    :crm-options="crmOptions"
                    :layouts="layouts"
                    :parents="parents"
                    :default-parent-id="defaultParentId"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>

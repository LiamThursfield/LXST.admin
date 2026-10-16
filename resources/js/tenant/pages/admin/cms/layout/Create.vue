<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { CrmOptions } from '@/tenant/components/cms/nestedTemplates';
import type { TemplateData } from '@/types';
import LayoutEditor from './components/LayoutEditor.vue';

defineProps<{
    templates: { data: TemplateData[] };
    nestedTemplates: { data: TemplateData[] };
    menuOptions: Array<{ value: number; label: string }>;
    crmOptions: CrmOptions | null;
}>();

const { trans } = useTrans();

const title = computed(() => trans('cms.layouts.create.title'));
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
                    :menus="menuOptions"
                    :crm-options="crmOptions"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>

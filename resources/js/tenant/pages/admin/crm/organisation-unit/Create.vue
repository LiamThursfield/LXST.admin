<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { OrganisationUnitFormOptions } from '@/types';
import OrganisationUnitForm from './components/OrganisationUnitForm.vue';

defineProps<OrganisationUnitFormOptions>();

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar
                :title="trans('crm.organisation_units.create.title')"
            >
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head :title="trans('crm.organisation_units.create.title')" />
            <div class="w-full max-w-4xl">
                <OrganisationUnitForm
                    :types="types"
                    :allowed-parent-types="allowedParentTypes"
                    :parent-options="parentOptions"
                    :social-media="socialMedia"
                    :create="true"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>

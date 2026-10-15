<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type {
    OrganisationUnitData,
    OrganisationUnitFormOptions,
} from '@/types';
import OrganisationUnitForm from './components/OrganisationUnitForm.vue';

defineProps<
    OrganisationUnitFormOptions & {
        organisationUnit: { data: OrganisationUnitData };
    }
>();

const { trans } = useTrans();
</script>

<template>
    <UDashboardPanel>
        <template #header>
            <UDashboardNavbar
                :title="
                    trans('crm.organisation_units.edit.title', {
                        name: organisationUnit.data.name,
                    })
                "
            >
                <template #leading>
                    <UDashboardSidebarCollapse as="button" :disabled="false" />
                </template>
            </UDashboardNavbar>
        </template>

        <template #body>
            <Head
                :title="
                    trans('crm.organisation_units.edit.title', {
                        name: organisationUnit.data.name,
                    })
                "
            />
            <div class="w-full max-w-4xl">
                <OrganisationUnitForm
                    :organisation-unit="organisationUnit.data"
                    :types="types"
                    :allowed-parent-types="allowedParentTypes"
                    :parent-options="parentOptions"
                    :social-media="socialMedia"
                    :readonly="false"
                />
            </div>
        </template>
    </UDashboardPanel>
</template>

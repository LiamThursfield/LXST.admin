<script setup lang="ts">
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
</script>

<template>
    <OrganisationUnitForm
        :organisation-unit="organisationUnit.data"
        :types="types"
        :allowed-parent-types="allowedParentTypes"
        :parent-options="parentOptions"
        :social-media="socialMedia"
        :readonly="false"
    />
</template>

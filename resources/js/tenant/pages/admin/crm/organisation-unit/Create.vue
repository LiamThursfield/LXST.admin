<script setup lang="ts">
import type { OrganisationUnitFormOptions } from '@/types';
import OrganisationUnitForm from './components/OrganisationUnitForm.vue';

defineProps<OrganisationUnitFormOptions>();
</script>

<template>
    <OrganisationUnitForm
        :types="types"
        :allowed-parent-types="allowedParentTypes"
        :parent-options="parentOptions"
        :social-media="socialMedia"
        :create="true"
    />
</template>

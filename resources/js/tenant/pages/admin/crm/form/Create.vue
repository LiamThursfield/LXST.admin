<script setup lang="ts">
import type { FormFieldTypeOption } from '@/types';
import CrmFormEditor from './components/CrmFormEditor.vue';

defineProps<{
    fieldTypes: FormFieldTypeOption[];
}>();
</script>

<template>
    <CrmFormEditor :field-types="fieldTypes" create />
</template>

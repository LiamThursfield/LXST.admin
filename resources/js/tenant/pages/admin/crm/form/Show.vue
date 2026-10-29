<script setup lang="ts">
import type { CrmFormData, FormFieldTypeOption } from '@/types';
import CrmFormEditor from './components/CrmFormEditor.vue';

defineProps<{
    form: { data: CrmFormData };
    fieldTypes: FormFieldTypeOption[];
}>();
</script>

<template>
    <CrmFormEditor :crm-form="form.data" :field-types="fieldTypes" readonly />
</template>

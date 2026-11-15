<script setup lang="ts">
import type { CrmFormData, FormFieldTypeOption } from '@/types';
import CrmFormEditor from './components/CrmFormEditor.vue';

defineProps<{
    form: { data: CrmFormData };
    fieldTypes: FormFieldTypeOption[];
    captchaConfigured: boolean;
}>();
</script>

<template>
    <CrmFormEditor
        :crm-form="form.data"
        :field-types="fieldTypes"
        :captcha-configured="captchaConfigured"
        readonly
    />
</template>

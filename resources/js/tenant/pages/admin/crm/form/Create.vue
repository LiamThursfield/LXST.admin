<script setup lang="ts">
import type { FormFieldTypeOption } from '@/types';
import CrmFormEditor from './components/CrmFormEditor.vue';

defineProps<{
    fieldTypes: FormFieldTypeOption[];
    captchaConfigured: boolean;
}>();
</script>

<template>
    <CrmFormEditor
        :field-types="fieldTypes"
        :captcha-configured="captchaConfigured"
        create
    />
</template>

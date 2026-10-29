<script setup lang="ts">
import type { ContactData } from '@/types';
import ContactForm from './components/ContactForm.vue';

defineProps<{
    contact: { data: ContactData };
}>();
</script>

<template>
    <ContactForm :contact="contact.data" :readonly="false" />
</template>

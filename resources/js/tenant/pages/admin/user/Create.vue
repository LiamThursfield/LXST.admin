<script setup lang="ts">
import UserForm from './components/UserForm.vue';

defineProps<{
    roles: Array<{ label: string; value: string }>;
}>();
</script>

<template>
    <UserForm :roles="roles" :create="true" />
</template>

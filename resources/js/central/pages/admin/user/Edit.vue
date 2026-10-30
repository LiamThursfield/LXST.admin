<script setup lang="ts">
import type { CentralUserData } from '@/types';
import UserForm from './components/UserForm.vue';

defineProps<{
    user: { data: CentralUserData };
}>();
</script>

<template>
    <UserForm :user="user.data" />
</template>

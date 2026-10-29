<script setup lang="ts">
import UserForm from './components/UserForm.vue';

defineProps<{
    user: {
        data: {
            id: number;
            first_name: string;
            last_name: string;
            email: string;
            roles?: string[];
        };
    };
    roles: Array<{ label: string; value: string }>;
}>();
</script>

<template>
    <UserForm :user="user.data" :roles="roles" :readonly="false" />
</template>

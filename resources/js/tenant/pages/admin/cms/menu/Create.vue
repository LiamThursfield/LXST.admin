<script setup lang="ts">
import type { MenuPageOption } from '@/types';
import MenuEditor from './components/MenuEditor.vue';

defineProps<{
    pages: MenuPageOption[];
}>();
</script>

<template>
    <MenuEditor :pages="pages" />
</template>

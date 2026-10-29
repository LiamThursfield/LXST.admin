<script setup lang="ts">
import type { MenuData, MenuPageOption } from '@/types';
import MenuEditor from './components/MenuEditor.vue';

defineProps<{
    menu: { data: MenuData };
    pages: MenuPageOption[];
}>();
</script>

<template>
    <MenuEditor :pages="pages" :menu="menu.data" />
</template>

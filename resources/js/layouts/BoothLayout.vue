<script lang="ts" setup>
import { useFlashToasts } from '@/composables/useFlashToasts';

// Full screen, without the sidebar, for pages left open in the DJ booth
useFlashToasts();
</script>

<template>
    <UApp>
        <div class="bg-default min-h-svh">
            <slot />
        </div>
    </UApp>
</template>

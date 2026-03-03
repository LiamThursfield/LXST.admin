<script setup lang="ts">
import type { LinkComponentBaseProps, Method } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';

defineProps<{
    href: LinkComponentBaseProps['href'];
    tabindex?: number;
    method?: Method;
    as?: string;
}>();
</script>

<template>
    <Link
        :href="href"
        :tabindex="tabindex"
        :method="method"
        :as="as"
        class="decoration-primary active:text-default active:decoration-current! hover:text-default hover:decoration-current! focus-visible:outline-primary underline underline-offset-4 transition-colors duration-300 ease-out"
    >
        <slot />
    </Link>
</template>

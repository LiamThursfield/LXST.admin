<script setup lang="ts">
import WebLayout from '@/central/layouts/WebLayout.vue';
defineOptions({ layout: WebLayout });
</script>

<template>
    <div>
        <UPageHero
            title="LXST.admin"
            description="A production-ready multi-tenant SaaS powered by Laravel, Inertia, Vue3, Nuxt UI, and Tailwind CSS."
            :links="[
                {
                    label: 'View on GitHub',
                    to: 'https://github.com/liamthursfield/LXST.admin',
                    target: '_blank',
                    icon: 'simple-icons:github',
                    color: 'neutral',
                    variant: 'outline',
                },
            ]"
        />
    </div>
</template>

<style scoped></style>

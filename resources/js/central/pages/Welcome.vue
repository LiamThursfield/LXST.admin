<script setup lang="ts">
import WebLayout from '@/central/layouts/WebLayout.vue';

const { trans } = useTrans();
defineOptions({ layout: WebLayout });
</script>

<template>
    <div>
        <UPageHero
            title="LXST.admin"
            :description="trans('common.welcome.description')"
            :links="[
                {
                    label: trans('common.welcome.view_on_github'),
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

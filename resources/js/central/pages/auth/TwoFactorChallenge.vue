<script lang="ts" setup>
import { Head } from '@inertiajs/vue3';
import TwoFactorAuthenticatedSessionController from '@/actions/App/Http/Controllers/Central/Fortify/TwoFactorAuthenticatedSessionController';
import AuthLayout from '@/layouts/AuthLayout.vue';

const { trans } = useTrans();

defineOptions({
    layout: (h: any, page: any) => {
        return h(
            AuthLayout,
            {
                title: useTrans().trans('auth.two_factor_challenge.title'),
            },
            () => page,
        );
    },
});
</script>

<template>
    <div>
        <Head :title="trans('auth.two_factor_challenge.head')" />

        <TwoFactorChallengeForm
            :action="TwoFactorAuthenticatedSessionController.store"
        />
    </div>
</template>

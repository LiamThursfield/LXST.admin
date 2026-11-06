<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';

defineOptions({ layout: PublicLayout });

const props = defineProps<{
    business: string;
    status: 'requested' | 'saved' | 'expired';
    expired: boolean;
    service: string;
    /** Set while the link can still be used. */
    saveUrl: string | null;
}>();

const { trans } = useTrans();

const message = computed(() => {
    if (props.status === 'saved') {
        return trans('bookings.payments.public.card_saved', {
            service: props.service,
        });
    }

    return props.expired
        ? trans('bookings.payments.public.card_expired', {
              business: props.business,
          })
        : trans('bookings.payments.public.card_requested');
});
</script>

<template>
    <div class="mx-auto flex max-w-md flex-col items-center gap-6 px-4 py-16">
        <Head :title="trans('bookings.payments.public.card_title')" />

        <UIcon
            :name="
                status === 'saved'
                    ? 'i-lucide-circle-check'
                    : 'i-lucide-credit-card'
            "
            class="size-12"
            :class="status === 'saved' ? 'text-success' : 'text-muted'"
        />

        <h1 class="text-xl font-semibold">{{ business }}</h1>

        <p class="text-center">{{ message }}</p>

        <UButton
            v-if="saveUrl"
            :href="saveUrl"
            size="lg"
            icon="i-lucide-lock"
            :label="trans('bookings.payments.public.save_card')"
        />
    </div>
</template>

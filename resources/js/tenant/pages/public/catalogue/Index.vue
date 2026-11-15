<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { PublicCatalogueSummary } from '@/types';

defineOptions({ layout: PublicLayout });

/**
 * Every published catalogue, those that apply now first, e.g. for one QR code on a restaurant table
 * that leads to whichever menu is being served
 */
defineProps<{
    business: string;
    catalogues: PublicCatalogueSummary[];
}>();

const { trans } = useTrans();
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="trans('catalogue.public.index_title')" />

        <h1 class="text-highlighted text-center text-3xl font-bold">
            {{ trans('catalogue.public.index_title') }}
        </h1>

        <p v-if="!catalogues.length" class="text-muted text-center">
            {{ trans('catalogue.public.empty') }}
        </p>

        <Link
            v-for="catalogue in catalogues"
            :key="catalogue.url"
            :href="catalogue.url"
            class="border-default hover:bg-elevated/50 flex items-center gap-3 rounded-lg border p-4 transition-colors"
        >
            <div class="flex min-w-0 flex-1 flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-highlighted font-semibold">
                        {{ catalogue.name }}
                    </span>
                    <UBadge
                        v-if="catalogue.applies_now"
                        color="primary"
                        variant="subtle"
                        size="sm"
                        :label="trans('catalogue.public.now')"
                    />
                </div>
                <p v-if="catalogue.description" class="text-muted text-sm">
                    {{ catalogue.description }}
                </p>
                <p v-if="catalogue.schedule" class="text-muted text-xs">
                    {{ catalogue.schedule }}
                </p>
            </div>
            <UIcon name="i-lucide-chevron-right" class="text-muted shrink-0" />
        </Link>
    </div>
</template>

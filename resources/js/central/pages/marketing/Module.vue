<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import ModuleMock from '@/central/components/marketing/ModuleMock.vue';
import WebLayout from '@/central/layouts/WebLayout.vue';
import {
    marketingModules,
    moduleIcons,
    useTranslationValue,
    type MarketingFeatureGroup,
    type MarketingModule,
} from '@/lib/marketing';
import { home } from '@/routes/central';
import { show as moduleShow } from '@/routes/central/modules';

defineOptions({ layout: WebLayout });

const props = defineProps<{ module: MarketingModule }>();

const { trans } = useTrans();
const translationValue = useTranslationValue();

const key = computed(() => `marketing.modules.${props.module}`);

const highlights = computed(() =>
    translationValue<string[]>(`${key.value}.highlights`, []),
);

const featureGroups = computed(() =>
    translationValue<MarketingFeatureGroup[]>(`${key.value}.features`, []),
);

const worksWith = computed(() =>
    Object.entries(
        translationValue<Record<string, string>>(`${key.value}.works_with`, {}),
    ).map(([module, reason]) => ({
        key: module,
        name: trans(`marketing.modules.${module}.name`),
        reason,
        icon: moduleIcons[module as MarketingModule],
        href: moduleShow.url(module),
    })),
);

const nextModule = computed(() => {
    const position = marketingModules.indexOf(props.module);

    return marketingModules[(position + 1) % marketingModules.length];
});
</script>

<template>
    <div>
        <Head :title="trans(`${key}.name`)" />

        <UContainer class="pb-10 pt-12 sm:pt-16">
            <nav
                class="text-muted flex items-center gap-1.5 text-sm"
                :aria-label="trans('marketing.module.all_modules')"
            >
                <Link
                    :href="`${home.url()}#modules`"
                    class="hover:text-highlighted transition-colors"
                >
                    {{ trans('marketing.nav.modules') }}
                </Link>
                <UIcon name="i-lucide-chevron-right" class="size-3.5" />
                <span class="text-highlighted" aria-current="page">
                    {{ trans(`${key}.name`) }}
                </span>
            </nav>

            <div class="mt-8 flex items-center gap-3">
                <span
                    class="border-default text-primary flex size-10 items-center justify-center rounded-lg border"
                >
                    <UIcon :name="moduleIcons[module]" class="size-5" />
                </span>
                <h1 class="text-highlighted text-xl font-semibold">
                    {{ trans(`${key}.name`) }}
                </h1>
            </div>

            <p
                class="text-highlighted mt-6 max-w-3xl text-balance text-4xl font-semibold tracking-tight sm:text-5xl"
            >
                {{ trans(`${key}.tagline`) }}
            </p>
            <p class="text-muted mt-6 max-w-2xl text-pretty text-lg">
                {{ trans(`${key}.intro`) }}
            </p>

            <ul class="text-toned mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                <li
                    v-for="highlight in highlights"
                    :key="highlight"
                    class="flex items-center gap-1.5"
                >
                    <span class="bg-primary size-1 rounded-full" />
                    {{ highlight }}
                </li>
            </ul>
        </UContainer>

        <UContainer class="pb-20">
            <div class="mx-auto max-w-4xl">
                <ModuleMock :module="module" />
            </div>
        </UContainer>

        <section class="border-default border-t">
            <UContainer
                class="grid gap-12 py-20 sm:py-24 lg:grid-cols-[minmax(0,1fr)_minmax(0,2.4fr)]"
            >
                <h2
                    class="text-highlighted text-2xl font-semibold tracking-tight"
                >
                    {{ trans('marketing.module.features') }}
                </h2>

                <div class="grid gap-x-10 gap-y-12 sm:grid-cols-2">
                    <div v-for="group in featureGroups" :key="group.title">
                        <h3
                            class="text-highlighted border-default border-b pb-3 font-semibold"
                        >
                            {{ group.title }}
                        </h3>
                        <ul class="mt-4 space-y-2.5">
                            <li
                                v-for="item in group.items"
                                :key="item"
                                class="text-muted flex gap-3 text-pretty text-sm"
                            >
                                <UIcon
                                    name="i-lucide-check"
                                    class="text-primary mt-0.5 size-4 shrink-0"
                                />
                                {{ item }}
                            </li>
                        </ul>
                    </div>
                </div>
            </UContainer>
        </section>

        <section
            v-if="worksWith.length"
            class="bg-elevated/40 border-default border-y"
        >
            <UContainer
                class="grid gap-12 py-20 sm:py-24 lg:grid-cols-[minmax(0,1fr)_minmax(0,2.4fr)]"
            >
                <h2
                    class="text-highlighted text-2xl font-semibold tracking-tight"
                >
                    {{ trans('marketing.module.works_with') }}
                </h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="other in worksWith"
                        :key="other.key"
                        :href="other.href"
                        class="border-default bg-default hover:border-primary/60 group flex gap-4 rounded-xl border p-5 transition-colors"
                    >
                        <UIcon
                            :name="other.icon"
                            class="text-toned group-hover:text-primary mt-0.5 size-5 shrink-0 transition-colors"
                        />
                        <span>
                            <span class="text-highlighted block font-semibold">
                                {{ other.name }}
                            </span>
                            <span class="text-muted mt-1 block text-sm">
                                {{ other.reason }}
                            </span>
                        </span>
                    </Link>
                </div>
            </UContainer>
        </section>

        <UContainer class="py-16">
            <Link
                :href="moduleShow.url(nextModule)"
                class="group flex items-center justify-between gap-6"
            >
                <span>
                    <span class="text-muted block text-sm">
                        {{ trans('marketing.module.next') }}
                    </span>
                    <span
                        class="text-highlighted group-hover:text-primary mt-1 block text-3xl font-semibold tracking-tight transition-colors"
                    >
                        {{ trans(`marketing.modules.${nextModule}.name`) }}
                    </span>
                </span>
                <UIcon
                    name="i-lucide-arrow-right"
                    class="text-dimmed group-hover:text-primary size-8 transition-transform group-hover:translate-x-1"
                />
            </Link>
        </UContainer>
    </div>
</template>

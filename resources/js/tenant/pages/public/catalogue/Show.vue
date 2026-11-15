<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PublicLayout from '@/layouts/PublicLayout.vue';
import type { PublicCatalogue, PublicCatalogueItem } from '@/types';

defineOptions({ layout: PublicLayout });

/**
 * A catalogue's own page, for a link or a QR code, laid out to print or save as a PDF from the
 * browser. Opened with `?print=1`, the print dialog opens straight away.
 */
const props = defineProps<{
    business: string;
    catalogue: PublicCatalogue;
    /** Only staff can see a draft, to check it before it's published. */
    isDraft: boolean;
    print: boolean;
    indexUrl: string;
}>();

const { trans } = useTrans();

function price(item: PublicCatalogueItem): string | null {
    if (!item.price) {
        return null;
    }

    return item.price.from
        ? trans('catalogue.items.price_from', {
              price: item.price.formatted,
          })
        : item.price.formatted;
}

function printPage() {
    window.print();
}

/** Prints in the light theme, so it's dark text on white paper whichever theme the screen uses. */
let wasDark = false;

function switchToLightTheme() {
    wasDark = document.documentElement.classList.contains('dark');
    document.documentElement.classList.remove('dark');
}

function restoreTheme() {
    if (wasDark) {
        document.documentElement.classList.add('dark');
    }
}

onMounted(() => {
    window.addEventListener('beforeprint', switchToLightTheme);
    window.addEventListener('afterprint', restoreTheme);

    if (props.print) {
        // Once the images have had a moment to load
        setTimeout(printPage, 300);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeprint', switchToLightTheme);
    window.removeEventListener('afterprint', restoreTheme);
});
</script>

<template>
    <div class="flex flex-col gap-6">
        <Head :title="catalogue.name" />

        <UAlert
            v-if="isDraft"
            color="warning"
            variant="subtle"
            icon="i-lucide-eye-off"
            :description="trans('catalogue.public.draft')"
            class="print:hidden"
        />

        <div class="flex items-center justify-between gap-3 print:hidden">
            <Link
                :href="indexUrl"
                class="text-muted hover:text-default flex items-center gap-1 text-sm"
            >
                <UIcon name="i-lucide-arrow-left" />
                {{ trans('catalogue.public.all') }}
            </Link>
            <UButton
                icon="i-lucide-printer"
                color="neutral"
                variant="outline"
                size="sm"
                :label="trans('catalogue.public.print')"
                @click="printPage"
            />
        </div>

        <header class="flex flex-col gap-2 text-center">
            <p class="text-muted hidden text-sm print:block">{{ business }}</p>
            <h1 class="text-highlighted text-3xl font-bold">
                {{ catalogue.name }}
            </h1>
            <p v-if="catalogue.description" class="text-muted">
                {{ catalogue.description }}
            </p>
            <p
                v-if="catalogue.schedule.summary"
                class="text-muted flex items-center justify-center gap-1 text-sm"
            >
                <UIcon name="i-lucide-calendar-clock" class="shrink-0" />
                {{ catalogue.schedule.summary }}
            </p>
            <div v-if="!catalogue.applies_now && !isDraft" class="print:hidden">
                <UBadge
                    color="neutral"
                    variant="subtle"
                    :label="trans('catalogue.public.not_now')"
                />
            </div>
        </header>

        <p v-if="!catalogue.sections.length" class="text-muted text-center">
            {{ trans('catalogue.public.empty_catalogue') }}
        </p>

        <section
            v-for="section in catalogue.sections"
            :key="section.name"
            class="flex flex-col gap-4"
        >
            <div class="border-default break-after-avoid border-b pb-2">
                <h2 class="text-highlighted text-xl font-semibold">
                    {{ section.name }}
                </h2>
                <p v-if="section.description" class="text-muted text-sm">
                    {{ section.description }}
                </p>
            </div>

            <article
                v-for="item in section.items"
                :key="item.name"
                class="flex break-inside-avoid gap-3"
                :class="{ 'opacity-60': !item.available }"
            >
                <img
                    v-if="item.image"
                    :src="item.image.url"
                    :alt="item.image.alt ?? ''"
                    class="bg-elevated size-20 shrink-0 rounded-md object-cover print:hidden"
                />
                <div class="flex min-w-0 flex-1 flex-col gap-1">
                    <div class="flex items-baseline gap-2">
                        <h3 class="text-highlighted font-medium">
                            {{ item.name }}
                        </h3>
                        <UBadge
                            v-if="!item.available"
                            color="neutral"
                            variant="subtle"
                            size="sm"
                            :label="
                                trans(
                                    item.sold_out
                                        ? 'catalogue.items.sold_out'
                                        : 'catalogue.items.out_of_stock',
                                )
                            "
                            class="print:hidden"
                        />
                        <span
                            v-if="price(item)"
                            class="text-highlighted ml-auto shrink-0 font-medium"
                        >
                            {{ price(item) }}
                        </span>
                    </div>
                    <p v-if="item.description" class="text-muted text-sm">
                        {{ item.description }}
                    </p>
                    <p
                        v-if="item.service?.duration_minutes"
                        class="text-muted text-xs"
                    >
                        {{
                            trans('catalogue.public.minutes', {
                                count: String(item.service.duration_minutes),
                            })
                        }}
                    </p>
                    <ul
                        v-if="item.variations.length"
                        class="flex flex-wrap gap-x-4 gap-y-1 text-sm"
                    >
                        <li
                            v-for="variation in item.variations"
                            :key="variation.name"
                            :class="{
                                'text-muted line-through': !variation.in_stock,
                            }"
                        >
                            {{ variation.name }}
                            <span class="font-medium">{{
                                variation.price.formatted
                            }}</span>
                        </li>
                    </ul>
                    <ul
                        v-if="item.tags.length"
                        class="text-muted flex flex-wrap gap-x-3 gap-y-1 text-xs"
                    >
                        <li
                            v-for="tag in item.tags"
                            :key="tag.name"
                            class="flex items-center gap-1"
                            :class="{
                                'text-warning': tag.kind === 'allergen',
                            }"
                        >
                            <UIcon v-if="tag.icon" :name="tag.icon" />
                            {{ tag.name }}
                        </li>
                    </ul>
                </div>
            </article>

            <p v-if="!section.items.length" class="text-muted text-sm">
                {{ trans('catalogue.sections.no_items') }}
            </p>
        </section>
    </div>
</template>

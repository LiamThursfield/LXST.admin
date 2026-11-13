<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    edit,
    index,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Catalogue/CatalogueController';
import { update as updateStock } from '@/actions/App/Http/Controllers/Tenant/Admin/Catalogue/ItemStockController';
import { catalogueItemPrice, formatCatalogueMoney } from '@/lib/catalogue';
import CatalogueTagList from '@/tenant/components/catalogue/CatalogueTagList.vue';
import type {
    CatalogueData,
    CatalogueItemData,
    CatalogueTagOption,
} from '@/types';

/**
 * A catalogue as it reads, section by section. Staff who can manage stock can mark items sold out
 * and count their stock here, each change saved as it's made.
 */
const props = defineProps<{
    catalogue: { data: CatalogueData };
    tags: CatalogueTagOption[];
    currency: string;
    canEdit: boolean;
    canUpdateStock: boolean;
}>();

const { trans } = useTrans();

const catalogue = computed(() => props.catalogue.data);

const breadcrumbs = computed(() => [
    { label: trans('catalogue.catalogues.index.title'), to: index.url() },
    { label: catalogue.value.name },
]);

const saving = ref<number | null>(null);

type StockChange = {
    is_available?: boolean;
    stock?: number;
    variations?: Array<{ id: number | null; stock: number }>;
};

function save(item: CatalogueItemData, data: StockChange) {
    if (item.id === null) {
        return;
    }

    router.patch(updateStock.url([catalogue.value.id, item.id]), data, {
        preserveScroll: true,
        onStart: () => (saving.value = item.id),
        onFinish: () => (saving.value = null),
    });
}

/** Stock changes wait for a pause, so pressing + a few times saves once. */
const timers = new Map<string, ReturnType<typeof setTimeout>>();

function saveStockSoon(
    key: string,
    item: CatalogueItemData,
    data: StockChange,
) {
    clearTimeout(timers.get(key));
    timers.set(
        key,
        setTimeout(() => {
            timers.delete(key);
            save(item, data);
        }, 600),
    );
}

function saveItemStock(item: CatalogueItemData, stock: number | null) {
    saveStockSoon(`item-${item.id}`, item, {
        stock: Math.max(0, stock ?? 0),
    });
}

function saveVariationStock(
    item: CatalogueItemData,
    variationId: number | null,
    stock: number | null,
) {
    saveStockSoon(`variation-${variationId}`, item, {
        variations: [{ id: variationId, stock: Math.max(0, stock ?? 0) }],
    });
}
</script>

<template>
    <EditorLayout :title="catalogue.name" :breadcrumbs="breadcrumbs" readonly>
        <template #badges>
            <UBadge
                :color="catalogue.is_published ? 'success' : 'neutral'"
                variant="subtle"
                :label="
                    trans(
                        catalogue.is_published
                            ? 'catalogue.catalogues.status.published'
                            : 'catalogue.catalogues.status.draft',
                    )
                "
            />
            <UBadge
                :color="catalogue.applies_now ? 'primary' : 'neutral'"
                variant="subtle"
                icon="i-lucide-clock"
                :label="
                    trans(
                        catalogue.applies_now
                            ? 'catalogue.catalogues.status.applies_now'
                            : 'catalogue.catalogues.status.not_now',
                    )
                "
            />
        </template>

        <template #actions>
            <UButton
                v-if="canEdit"
                as="Link"
                :href="edit.url(catalogue.id)"
                icon="i-lucide-pencil"
                :label="trans('catalogue.catalogues.edit_button')"
            />
        </template>

        <UPageCard variant="subtle">
            <div class="flex flex-col gap-1">
                <h2 class="text-highlighted text-lg font-semibold">
                    {{ catalogue.name }}
                </h2>
                <p v-if="catalogue.description" class="text-muted text-sm">
                    {{ catalogue.description }}
                </p>
                <p class="text-muted text-sm">
                    <UIcon
                        name="i-lucide-calendar-clock"
                        class="align-middle"
                    />
                    {{
                        catalogue.schedule ??
                        trans('catalogue.catalogues.always')
                    }}
                </p>
            </div>

            <p v-if="!catalogue.sections?.length" class="text-muted text-sm">
                {{ trans('catalogue.sections.empty') }}
            </p>

            <section
                v-for="section in catalogue.sections"
                :key="section.id ?? section.name"
                class="flex flex-col gap-3"
            >
                <div class="border-default border-b pb-1">
                    <h3 class="text-highlighted font-semibold">
                        {{ section.name }}
                    </h3>
                    <p v-if="section.description" class="text-muted text-sm">
                        {{ section.description }}
                    </p>
                </div>

                <p v-if="!section.items.length" class="text-muted text-sm">
                    {{ trans('catalogue.sections.no_items') }}
                </p>

                <article
                    v-for="item in section.items"
                    :key="item.id ?? item.name"
                    class="flex gap-3"
                    :class="{
                        'opacity-60': !item.is_available || !item.in_stock,
                    }"
                >
                    <img
                        v-if="item.image_url"
                        :src="item.image_url"
                        alt=""
                        class="bg-elevated size-16 shrink-0 rounded-md object-cover"
                    />
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <div class="flex flex-wrap items-baseline gap-2">
                            <span class="font-medium">{{ item.name }}</span>
                            <UBadge
                                v-if="!item.is_available"
                                color="neutral"
                                variant="subtle"
                                size="sm"
                                :label="trans('catalogue.items.sold_out')"
                            />
                            <UBadge
                                v-else-if="!item.in_stock"
                                color="warning"
                                variant="subtle"
                                size="sm"
                                :label="trans('catalogue.items.out_of_stock')"
                            />
                            <span class="text-highlighted ml-auto text-sm">
                                {{
                                    catalogueItemPrice(
                                        item.display_price,
                                        currency,
                                    )
                                }}
                            </span>
                        </div>
                        <p v-if="item.description" class="text-muted text-sm">
                            {{ item.description }}
                        </p>
                        <p v-if="item.service_name" class="text-muted text-xs">
                            <UIcon
                                name="i-lucide-calendar-check"
                                class="align-middle"
                            />
                            {{ item.service_name }}
                        </p>
                        <ul
                            v-if="item.variations.length"
                            class="text-muted flex flex-wrap gap-x-4 gap-y-1 text-sm"
                        >
                            <li
                                v-for="variation in item.variations"
                                :key="variation.id ?? variation.name"
                            >
                                {{ variation.name }}
                                {{
                                    formatCatalogueMoney(
                                        variation.price_minor ?? 0,
                                        currency,
                                    )
                                }}
                                <template
                                    v-if="item.tracks_stock && !canUpdateStock"
                                >
                                    ·
                                    {{
                                        trans('catalogue.items.stock_left', {
                                            count: String(variation.stock ?? 0),
                                        })
                                    }}
                                </template>
                            </li>
                        </ul>
                        <CatalogueTagList
                            :tag-ids="item.tag_ids"
                            :tags="tags"
                        />

                        <div
                            v-if="canUpdateStock"
                            class="bg-elevated/50 mt-1 flex flex-wrap items-center gap-x-5 gap-y-2 rounded-md px-3 py-2"
                        >
                            <USwitch
                                :model-value="item.is_available"
                                :label="trans('catalogue.stock.available')"
                                :loading="saving === item.id"
                                size="sm"
                                @update:model-value="
                                    save(item, { is_available: $event })
                                "
                            />
                            <template v-if="item.tracks_stock">
                                <label
                                    v-if="!item.variations.length"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    {{ trans('catalogue.stock.stock') }}
                                    <UInputNumber
                                        :model-value="item.stock ?? 0"
                                        :min="0"
                                        size="sm"
                                        class="w-28"
                                        @update:model-value="
                                            saveItemStock(item, $event)
                                        "
                                    />
                                </label>
                                <label
                                    v-for="variation in item.variations"
                                    v-else
                                    :key="variation.id ?? variation.name"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    {{ variation.name }}
                                    <UInputNumber
                                        :model-value="variation.stock ?? 0"
                                        :min="0"
                                        size="sm"
                                        class="w-28"
                                        @update:model-value="
                                            saveVariationStock(
                                                item,
                                                variation.id,
                                                $event,
                                            )
                                        "
                                    />
                                </label>
                            </template>
                        </div>
                        <p
                            v-else-if="
                                item.tracks_stock && !item.variations.length
                            "
                            class="text-muted text-xs"
                        >
                            {{
                                trans('catalogue.items.stock_left', {
                                    count: String(item.stock ?? 0),
                                })
                            }}
                        </p>
                    </div>
                </article>
            </section>
        </UPageCard>
    </EditorLayout>
</template>

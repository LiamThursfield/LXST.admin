<script setup lang="ts">
import FileField from '@/tenant/components/file-manager/FileField.vue';
import type {
    CatalogueItemData,
    CatalogueServiceOption,
    CatalogueTagOption,
} from '@/types';

/**
 * One catalogue item's fields, edited in place: its name and description, a service it can show,
 * its price or variations, image, tags, and whether it's available and counts its stock.
 */
const props = defineProps<{
    tags: CatalogueTagOption[];
    /** Null without the Bookings module, or for those who can't see services. */
    services: CatalogueServiceOption[] | null;
    currency: string;
    /** Where its server errors are, e.g. `sections.0.items.2`. */
    errorPrefix: string;
    errors: Record<string, string | undefined>;
}>();

const item = defineModel<CatalogueItemData>({ required: true });

const { trans } = useTrans();

const error = (field: string) => props.errors[`${props.errorPrefix}.${field}`];

const serviceItems = computed(() => [
    { value: null, label: trans('catalogue.items.fields.no_service') },
    ...(props.services ?? []).map(({ value, label }) => ({ value, label })),
]);

const tagItems = computed(() =>
    props.tags.map((tag) => ({
        value: tag.value,
        label: tag.label,
        icon: tag.icon ?? undefined,
    })),
);

const service = computed(
    () =>
        props.services?.find(
            (option) => option.value === item.value.service_id,
        ) ?? null,
);

const hasVariations = computed(() => item.value.variations.length > 0);

/** A service or variations set the price, so the item's own isn't asked for. */
const asksForPrice = computed(
    () => item.value.service_id === null && !hasVariations.value,
);

const money = (minor: number) =>
    new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency: props.currency,
    }).format(minor / 100);

function chooseService(id: number | null) {
    item.value.service_id = id;

    // The service sets the price, so variations don't apply
    if (id !== null) {
        item.value.variations = [];
    }

    // Starts with the service's name, until it's typed
    if (id !== null && item.value.name.trim() === '') {
        item.value.name = service.value?.label ?? '';
    }
}

function addVariation() {
    item.value.variations.push({
        id: null,
        name: '',
        price: item.value.price ?? '',
        stock: item.value.tracks_stock ? 0 : null,
    });
}

function removeVariation(index: number) {
    item.value.variations.splice(index, 1);
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <UFormField
            :label="trans('catalogue.items.fields.name')"
            :error="error('name')"
            required
        >
            <UInput v-model="item.name" class="w-full" autofocus />
        </UFormField>

        <UFormField
            :label="trans('catalogue.items.fields.description')"
            :error="error('description')"
        >
            <UTextarea
                v-model="item.description as string"
                :rows="3"
                autoresize
                class="w-full"
            />
        </UFormField>

        <UFormField
            v-if="services !== null"
            :label="trans('catalogue.items.fields.service')"
            :help="trans('catalogue.items.fields.service_help')"
            :error="error('service_id')"
        >
            <USelectMenu
                :model-value="item.service_id"
                :items="serviceItems"
                value-key="value"
                class="w-full"
                @update:model-value="chooseService"
            />
        </UFormField>

        <UFormField
            v-if="asksForPrice"
            :label="trans('catalogue.items.fields.price', { currency })"
            :help="trans('catalogue.items.fields.price_help')"
            :error="error('price')"
        >
            <UInput
                v-model="item.price as string"
                type="number"
                min="0"
                step="0.01"
                class="w-full"
            />
        </UFormField>
        <p v-else-if="service" class="text-muted text-sm">
            {{ trans('catalogue.items.service_price') }}:
            {{
                service.price === null
                    ? trans('catalogue.items.no_price')
                    : service.from
                      ? trans('catalogue.items.price_from', {
                            price: money(service.price),
                        })
                      : money(service.price)
            }}
        </p>

        <div v-if="item.service_id === null" class="flex flex-col gap-3">
            <div>
                <p class="text-sm font-medium">
                    {{ trans('catalogue.items.fields.variations') }}
                </p>
                <p class="text-muted text-sm">
                    {{ trans('catalogue.items.fields.variations_help') }}
                </p>
            </div>

            <div
                v-for="(variation, index) in item.variations"
                :key="index"
                class="grid items-start gap-2"
                :class="
                    item.tracks_stock
                        ? 'grid-cols-[1fr_6rem_5rem_auto]'
                        : 'grid-cols-[1fr_6rem_auto]'
                "
            >
                <UFormField
                    :label="trans('catalogue.items.fields.variation_name')"
                    :error="error(`variations.${index}.name`)"
                    required
                >
                    <UInput v-model="variation.name" class="w-full" />
                </UFormField>
                <UFormField
                    :label="
                        trans('catalogue.items.fields.variation_price', {
                            currency,
                        })
                    "
                    :error="error(`variations.${index}.price`)"
                    required
                >
                    <UInput
                        v-model="variation.price"
                        type="number"
                        min="0"
                        step="0.01"
                        class="w-full"
                    />
                </UFormField>
                <UFormField
                    v-if="item.tracks_stock"
                    :label="trans('catalogue.items.fields.variation_stock')"
                    :error="error(`variations.${index}.stock`)"
                >
                    <UInput
                        v-model.number="variation.stock as number"
                        type="number"
                        min="0"
                        step="1"
                        class="w-full"
                    />
                </UFormField>
                <UButton
                    class="mt-6"
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-trash-2"
                    :aria-label="trans('catalogue.items.remove_variation')"
                    @click="removeVariation(index)"
                />
            </div>

            <UButton
                class="self-start"
                color="neutral"
                variant="subtle"
                size="sm"
                icon="i-lucide-plus"
                :label="trans('catalogue.items.add_variation')"
                @click="addVariation"
            />
        </div>

        <UFormField
            :label="trans('catalogue.items.fields.image')"
            :error="error('image_file_id')"
        >
            <FileField v-model="item.image_file_id" images-only />
        </UFormField>

        <UFormField
            :label="trans('catalogue.items.fields.tags')"
            :help="trans('catalogue.items.fields.tags_help')"
            :error="error('tag_ids')"
        >
            <USelectMenu
                v-model="item.tag_ids"
                :items="tagItems"
                value-key="value"
                multiple
                class="w-full"
            />
        </UFormField>

        <USwitch
            v-model="item.is_available"
            :label="trans('catalogue.items.fields.is_available')"
            :description="trans('catalogue.items.fields.is_available_help')"
        />

        <USwitch
            v-model="item.tracks_stock"
            :label="trans('catalogue.items.fields.tracks_stock')"
            :description="trans('catalogue.items.fields.tracks_stock_help')"
        />

        <UFormField
            v-if="item.tracks_stock && !hasVariations"
            :label="trans('catalogue.items.fields.stock')"
            :error="error('stock')"
        >
            <UInput
                v-model.number="item.stock as number"
                type="number"
                min="0"
                step="1"
                class="w-full"
            />
        </UFormField>
    </div>
</template>

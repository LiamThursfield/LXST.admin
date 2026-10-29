<script setup lang="ts">
/** A field type the picker offers. */
export type PickerFieldType = {
    value: string;
    label: string;
    description: string;
    icon: string;
    /** Listed but can't be chosen, e.g. a type whose content editor doesn't exist yet. */
    disabled?: boolean;
};

/** A heading in the picker and the types under it. */
export type PickerFieldTypeGroup = {
    label: string;
    types: PickerFieldType[];
};

/**
 * Chooses the type for a new field in a field builder: a searchable list of types, grouped (e.g.
 * Text, Media & links, Structure), each with a short description.
 */
const props = defineProps<{
    groups: PickerFieldTypeGroup[];
}>();

const emit = defineEmits<{
    select: [type: string];
}>();

const open = defineModel<boolean>('open', { required: true });

const { trans } = useTrans();

const search = ref('');

watch(open, (isOpen) => {
    if (isOpen) {
        search.value = '';
    }
});

const filteredGroups = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.groups
        .map((group) => ({
            ...group,
            types: term
                ? group.types.filter((type) =>
                      `${type.label} ${type.description}`
                          .toLowerCase()
                          .includes(term),
                  )
                : group.types,
        }))
        .filter((group) => group.types.length);
});

function choose(type: PickerFieldType) {
    if (type.disabled) {
        return;
    }

    emit('select', type.value);
    open.value = false;
}
</script>

<template>
    <UModal
        v-model:open="open"
        :title="trans('common.builder.picker.title')"
        :description="trans('common.builder.picker.description')"
        :ui="{ content: 'sm:max-w-2xl' }"
    >
        <template #body>
            <div class="flex flex-col gap-4">
                <UInput
                    v-model="search"
                    icon="i-lucide-search"
                    :placeholder="trans('common.builder.picker.search')"
                    :aria-label="trans('common.builder.picker.search')"
                    autofocus
                    class="w-full"
                />

                <p
                    v-if="!filteredGroups.length"
                    class="text-muted py-6 text-center text-sm"
                >
                    {{ trans('common.builder.picker.none') }}
                </p>

                <section
                    v-for="group in filteredGroups"
                    :key="group.label"
                    class="flex flex-col gap-2"
                >
                    <h3
                        class="text-muted text-xs font-semibold uppercase tracking-wide"
                    >
                        {{ group.label }}
                    </h3>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        <button
                            v-for="type in group.types"
                            :key="type.value"
                            type="button"
                            class="border-default hover:bg-elevated focus-visible:outline-primary flex items-start gap-3 rounded-md border p-3 text-left focus-visible:outline-2 disabled:cursor-not-allowed disabled:opacity-60"
                            :disabled="type.disabled"
                            @click="choose(type)"
                        >
                            <span
                                class="bg-elevated text-toned flex size-8 shrink-0 items-center justify-center rounded-md"
                            >
                                <UIcon :name="type.icon" class="size-4" />
                            </span>
                            <span class="min-w-0">
                                <span
                                    class="text-highlighted block text-sm font-medium"
                                >
                                    {{ type.label }}
                                </span>
                                <span class="text-muted block text-xs">
                                    {{ type.description }}
                                </span>
                            </span>
                        </button>
                    </div>
                </section>
            </div>
        </template>
    </UModal>
</template>

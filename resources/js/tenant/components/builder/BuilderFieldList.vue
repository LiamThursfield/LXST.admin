<script setup lang="ts" generic="T extends { uid: number }">
import { VueDraggable } from 'vue-draggable-plus';

/** What a field's row in the list shows. */
export type BuilderFieldRow = {
    name: string;
    slug: string;
    typeLabel: string;
    icon: string;
    required: boolean;
};

/**
 * The reorderable list of fields in a field builder (CRM forms, CMS templates), one compact row
 * each: a drag handle, the type's icon, the name and slug, the type, and a red dot when the field
 * has errors. Choosing a row selects the field, so its settings can be shown beside the list.
 * Each item needs a client-side `uid` so it keeps its identity while being dragged. Lists sharing
 * a `group` can drag fields between them, e.g. between a template's sections.
 */
const props = withDefaults(
    defineProps<{
        /** What each field's row shows. */
        describe: (item: T) => BuilderFieldRow;
        emptyText: string;
        /** Where this list's fields' server errors are, e.g. `fields` or `sections.1.fields`. */
        errorPrefix: string;
        errors?: Record<string, string | undefined>;
        /** An error for the list as a whole, e.g. too many fields. */
        error?: string;
        readonly?: boolean;
        /** Lists with the same group can drag fields between them. */
        group?: string;
    }>(),
    {
        errors: () => ({}),
        error: undefined,
        readonly: false,
        group: undefined,
    },
);

const items = defineModel<T[]>({ required: true });

/** The chosen field's uid, shared by every list in the builder. */
const selected = defineModel<number | null>('selected', { default: null });

const { trans } = useTrans();

function hasError(index: number): boolean {
    const prefix = `${props.errorPrefix}.${index}.`;

    return Object.entries(props.errors).some(
        ([key, message]) => message && key.startsWith(prefix),
    );
}
</script>

<template>
    <div class="flex flex-col gap-1">
        <p v-if="error" class="text-error px-2 text-sm">
            {{ error }}
        </p>

        <div class="relative">
            <VueDraggable
                v-model="items"
                :disabled="readonly"
                :group="group"
                handle=".drag-handle"
                :animation="150"
                class="flex flex-col gap-0.5"
                :class="{
                    'border-default min-h-11 rounded-md border border-dashed':
                        !items.length,
                }"
            >
                <div
                    v-for="(item, index) in items"
                    :key="item.uid"
                    class="group flex items-center gap-1 rounded-md"
                    :class="
                        selected === item.uid
                            ? 'bg-primary/10 ring-primary/40 ring'
                            : 'hover:bg-elevated'
                    "
                >
                    <UButton
                        v-if="!readonly"
                        class="drag-handle cursor-grab"
                        color="neutral"
                        variant="link"
                        size="sm"
                        icon="i-lucide-grip-vertical"
                        :aria-label="trans('common.builder.drag')"
                    />

                    <button
                        type="button"
                        class="focus-visible:outline-primary flex min-w-0 flex-1 items-center gap-2.5 rounded-md py-1.5 pr-2 text-left focus-visible:outline-2"
                        :class="{ 'pl-2': readonly }"
                        :aria-pressed="selected === item.uid"
                        @click="selected = item.uid"
                    >
                        <span
                            class="bg-elevated text-toned flex size-7 shrink-0 items-center justify-center rounded-md"
                        >
                            <UIcon :name="describe(item).icon" class="size-4" />
                        </span>
                        <span class="flex min-w-0 flex-1 flex-col">
                            <span
                                class="text-highlighted truncate text-sm font-medium"
                                :class="{
                                    'text-muted italic': !describe(item).name,
                                }"
                            >
                                {{
                                    describe(item).name ||
                                    trans('common.builder.untitled')
                                }}
                                <span
                                    v-if="describe(item).required"
                                    class="text-error"
                                    >*</span
                                >
                            </span>
                            <span
                                v-if="describe(item).slug"
                                class="text-muted truncate font-mono text-xs"
                            >
                                {{ describe(item).slug }}
                            </span>
                        </span>
                        <span
                            class="text-muted hidden shrink-0 text-xs sm:inline"
                        >
                            {{ describe(item).typeLabel }}
                        </span>
                        <span
                            v-if="hasError(index)"
                            class="bg-error size-2 shrink-0 rounded-full"
                            role="img"
                            :aria-label="trans('common.builder.has_errors')"
                        />
                    </button>
                </div>
            </VueDraggable>

            <p
                v-if="!items.length"
                class="text-muted pointer-events-none absolute inset-0 flex items-center justify-center px-3 text-center text-sm"
            >
                {{ emptyText }}
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
import type { Errors, Method } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import type { DropdownMenuItem } from '@nuxt/ui';
// Imported explicitly: the central app never uses UTooltip, so its generated component types lack it.
import UTooltip from '@nuxt/ui/components/Tooltip.vue';
import { useBreakpoints, breakpointsTailwind } from '@vueuse/core';
import { computed, ref } from 'vue';

const { trans } = useTrans();

const toast = useToast();

interface Action {
    label: string;
    url: string;
    method: Method;
    icon?: string;
    requireConfirmation: boolean;
    confirmationMessage?: string;
}

const props = withDefaults(
    defineProps<{
        actions: Action[];
        maxVisible?: number;
        /** The overflow menu's button as in a navbar, outlined and full size, rather than in a row. */
        toolbar?: boolean;
    }>(),
    {
        maxVisible: 3,
        toolbar: false,
    },
);

const breakpoints = useBreakpoints(breakpointsTailwind);
const isScreenGteMd = breakpoints.greaterOrEqual('md');

const confirmModalOpen = ref(false);
const activeAction = ref<Action | null>(null);

// We should show {maxVisible} actions on md & larger screens
const visibleActions = computed(() =>
    isScreenGteMd.value ? props.actions.slice(0, props.maxVisible) : [],
);

// All other items (or all items on screens less than md) should be in
// the overflow dropdown menu
const overflowActions = computed(() =>
    isScreenGteMd.value ? props.actions.slice(props.maxVisible) : props.actions,
);

const overflowItems = computed<DropdownMenuItem[][]>(() => [
    overflowActions.value.map((action) => ({
        label: action.label,
        icon: action.icon,
        onSelect(e: Event) {
            e.preventDefault();
            handleActionClick(action);
        },
    })),
]);

function handleActionClick(action: Action) {
    if (action.requireConfirmation) {
        activeAction.value = action;
        confirmModalOpen.value = true;
        return;
    }

    executeAction(action);
}

function executeAction(action: Action | null) {
    if (!action) return;

    if (action.method.toLowerCase() === 'get') {
        router.visit(action.url);
    } else {
        router.visit(action.url, {
            method: action.method,
            // e.g. a delete that is refused with a validation error
            onError: showErrorToasts,
        });
    }
}

function showErrorToasts(errors: Errors) {
    Object.values(errors).forEach((message) => {
        toast.add({
            title: message,
            icon: 'i-lucide-circle-alert',
            color: 'error',
        });
    });
}

function handleConfirm() {
    executeAction(activeAction.value);
    confirmModalOpen.value = false;
}
</script>

<template>
    <div class="flex items-center gap-1">
        <UTooltip
            v-for="action in visibleActions"
            :key="action.label"
            :text="action.label"
        >
            <UButton
                color="neutral"
                variant="ghost"
                square
                :icon="action.icon"
                size="sm"
                @click="handleActionClick(action)"
            />
        </UTooltip>

        <UDropdownMenu
            v-if="overflowActions.length"
            :items="overflowItems"
            :content="{ align: 'end' }"
        >
            <UButton
                v-if="toolbar"
                color="neutral"
                variant="outline"
                icon="i-lucide-ellipsis"
                square
                :aria-label="trans('common.actions.more')"
            />
            <UTooltip v-else :text="trans('common.actions.more')">
                <UButton
                    color="neutral"
                    variant="ghost"
                    icon="i-lucide-more-horizontal"
                    size="sm"
                    square
                />
            </UTooltip>
        </UDropdownMenu>

        <UModal
            v-model:open="confirmModalOpen"
            :title="
                activeAction?.label
                    ? trans('common.confirmation.title', {
                          action: activeAction.label,
                      })
                    : trans('common.confirmation.title_fallback')
            "
            :description="
                activeAction?.confirmationMessage ||
                trans('common.confirmation.default')
            "
        >
            <template #body>
                <div class="flex justify-end gap-2">
                    <UButton
                        color="neutral"
                        :label="trans('common.actions.cancel')"
                        variant="subtle"
                        @click="confirmModalOpen = false"
                    />
                    <UButton
                        color="error"
                        :label="trans('common.actions.confirm')"
                        variant="solid"
                        @click="handleConfirm"
                    />
                </div>
            </template>
        </UModal>
    </div>
</template>

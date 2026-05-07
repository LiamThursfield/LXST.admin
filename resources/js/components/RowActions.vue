<script setup lang="ts">
import type { Method } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import type { DropdownMenuItem } from '@nuxt/ui';
import { computed, ref } from 'vue';

interface Action {
    label: string;
    url: string;
    method: Method;
    icon?: string;
    requireConfirmation: boolean;
    confirmationMessage?: string;
}

const props = defineProps<{
    actions: Action[];
}>();

const confirmModalOpen = ref(false);
const activeAction = ref<Action | null>(null);

const items = computed<DropdownMenuItem[][]>(() => [
    props.actions.map((action) => ({
        label: action.label,
        icon: action.icon,
        onSelect(e: Event) {
            e.preventDefault();

            if (action.requireConfirmation) {
                activeAction.value = action;
                confirmModalOpen.value = true;
                return;
            }

            executeAction(action);
        },
    })),
]);

function executeAction(action: Action | null) {
    if (!action) return;

    if (action.method === 'GET' || action.method === 'get') {
        router.visit(action.url);
    } else {
        router.visit(action.url, { method: action.method });
    }
}

function handleConfirm() {
    executeAction(activeAction.value);
    confirmModalOpen.value = false;
}
</script>

<template>
    <div>
        <UDropdownMenu
            v-if="actions && actions.length"
            :items="items"
            :content="{ align: 'end' }"
        >
            <UButton
                color="neutral"
                variant="ghost"
                icon="i-lucide-more-horizontal"
                square
            />
        </UDropdownMenu>

        <UModal
            v-model:open="confirmModalOpen"
            :title="
                activeAction?.label
                    ? `Confirm ${activeAction.label}`
                    : 'Please confirm'
            "
            :description="
                activeAction?.confirmationMessage ||
                'Are you sure you want to perform this action?'
            "
        >
            <template #body>
                <div class="flex justify-end gap-2">
                    <UButton
                        color="neutral"
                        label="Cancel"
                        variant="subtle"
                        @click="confirmModalOpen = false"
                    />
                    <UButton
                        color="error"
                        label="Confirm"
                        variant="solid"
                        @click="handleConfirm"
                    />
                </div>
            </template>
        </UModal>
    </div>
</template>

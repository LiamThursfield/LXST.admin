<script setup lang="ts">
/**
 * A drawing of the tenant admin, framing one of the product mock-ups. It's decorative: the page
 * around it says what it shows, so screen readers skip it.
 */
export type MockNavItem = {
    label: string;
    icon: string;
    active?: boolean;
};

defineProps<{
    business: string;
    domain: string;
    title: string;
    nav: MockNavItem[];
}>();
</script>

<template>
    <div
        aria-hidden="true"
        class="border-default bg-default select-none overflow-hidden rounded-xl border shadow-[0_1px_0_0_rgb(0_0_0/0.04),0_24px_48px_-24px_rgb(0_0_0/0.18)] dark:shadow-none"
    >
        <div
            class="border-default bg-elevated/60 text-dimmed flex items-center gap-2 border-b px-4 py-2 text-xs"
        >
            <UIcon name="i-lucide-lock" class="size-3" />
            <span>{{ domain }}/admin</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-[11rem_1fr]">
            <aside
                class="border-default hidden flex-col gap-0.5 border-r p-3 text-sm sm:flex"
            >
                <p
                    class="text-highlighted mb-3 truncate px-2 text-sm font-semibold"
                >
                    {{ business }}
                </p>
                <div
                    v-for="item in nav"
                    :key="item.label"
                    class="flex items-center gap-2 rounded-md px-2 py-1.5"
                    :class="
                        item.active
                            ? 'bg-elevated text-highlighted font-medium'
                            : 'text-muted'
                    "
                >
                    <UIcon :name="item.icon" class="size-4 shrink-0" />
                    <span class="truncate">{{ item.label }}</span>
                </div>
            </aside>

            <div class="min-w-0">
                <div
                    class="border-default flex h-12 items-center justify-between gap-3 border-b px-4 sm:px-5"
                >
                    <p class="text-highlighted truncate font-semibold">
                        {{ title }}
                    </p>
                    <slot name="actions" />
                </div>
                <div class="p-4 sm:p-5">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

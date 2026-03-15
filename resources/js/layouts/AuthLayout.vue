<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
    variant?: 'outline' | 'solid' | 'soft' | 'subtle' | 'naked' | 'ghost';
    icon?: string;
}>();
</script>

<template>
    <div
        class="bg-muted flex flex-col  gap-6 items-center justify-center min-h-svh p-6 relative md:p-10"
    >
        <div class="absolute inset-0 w-full">
            <div class="h-[35vh] bg-primary" />
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320" class="fill-current -mt-px text-primary">
                <path fill-opacity="1" d="M0,64L120,69.3C240,75,480,85,720,85.3C960,85,1200,75,1320,69.3L1440,64L1440,0L1320,0C1200,0,960,0,720,0C480,0,240,0,120,0L0,0Z"></path></svg>
        </div>
        <UPageCard :variant="variant ?? 'outline'" class=" dark w-full max-w-md">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <div class="flex flex-col items-center gap-2 font-medium">
                        <div
                            v-if="icon != null"
                            class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                        >
                            <UIcon
                                :name="icon"
                                class="text-foreground size-9"
                            />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </div>

                    <div class="space-y-2 text-center">
                        <h1 class="text-xl font-medium">{{ title }}</h1>
                        <p class="text-muted text-center text-sm">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </UPageCard>
    </div>
</template>

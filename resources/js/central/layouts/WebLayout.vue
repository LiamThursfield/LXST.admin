<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <UApp>
        <UHeader>
            <template #left>
                <Link
                    class="active:text-primary hover:text-primary text-lg transition-colors duration-300 ease-in-out"
                    to="/"
                >
                    <div class="flex flex-row">
                        <span class="font-black">LXST</span>
                        <span class="font-semibold">.admin</span>
                    </div>
                </Link>
            </template>

            <template #right>
                <UColorModeButton />

                <UButton
                    to="https://github.com/LiamThursfield/LXST.admin"
                    target="_blank"
                    icon="simple-icons:github"
                    aria-label="GitHub"
                    color="neutral"
                    variant="ghost"
                />
            </template>
        </UHeader>

        <UMain>
            <slot />
        </UMain>

        <USeparator label="LXST.admin" />

        <UFooter>
            <template #left>
                <p class="text-muted text-sm">
                    Built by LXST.digital • © {{ new Date().getFullYear() }}
                </p>
            </template>

            <template #right>
                <UButton
                    to="https://github.com/LiamThursfield/LXST.admin"
                    target="_blank"
                    icon="simple-icons:github"
                    aria-label="GitHub"
                    color="neutral"
                    variant="ghost"
                />
            </template>
        </UFooter>
    </UApp>
</template>

<style scoped></style>

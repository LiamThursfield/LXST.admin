<!-- This is taken from: https://github.com/jkque/laravel-nuxt-ui-starter-kit/ -->
<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { formatTimeAgo } from '@vueuse/core';
import { useDashboard } from '@/composables/useDashboard';
import type { Notification } from '@/types';

const { isNotificationsSlideoverOpen } = useDashboard();

// TODO: Implement notification api
// const { data: notifications } = useFetch('/api/notifications', { initialData: [] }).json<Notification[]>()
const notifications = ref<Notification[]>([]);
</script>

<template>
    <USlideover
        v-model:open="isNotificationsSlideoverOpen"
        title="Notifications"
    >
        <template #body>
            <Link
                v-for="notification in notifications"
                :key="notification.id"
                :to="`/inbox?id=${notification.id}`"
                class="hover:bg-elevated/50 relative -mx-3 flex items-center gap-3 rounded-md px-3 py-2.5 first:-mt-3 last:-mb-3"
            >
                <UChip color="error" :show="!!notification.unread" inset>
                    <UAvatar
                        v-bind="notification.sender.avatar"
                        :alt="notification.sender.name"
                        size="md"
                    />
                </UChip>

                <div class="flex-1 text-sm">
                    <p class="flex items-center justify-between">
                        <span class="text-highlighted font-medium">{{
                            notification.sender.name
                        }}</span>

                        <time
                            :datetime="notification.date"
                            class="text-muted text-xs"
                            v-text="formatTimeAgo(new Date(notification.date))"
                        />
                    </p>

                    <p class="text-dimmed">
                        {{ notification.body }}
                    </p>
                </div>
            </Link>

            <p v-if="notifications.length === 0">
                You currently don't have any notifications.
            </p>
        </template>
    </USlideover>
</template>

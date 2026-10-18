<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    destroy,
    update,
} from '@/actions/App/Http/Controllers/Tenant/Admin/Dj/GuestPassController';
import { formatDateTime, userTimezone } from '@/lib/timezone';
import type { GuestPassData } from '@/types';

const props = defineProps<{
    passes: GuestPassData[];
    title: string;
    description: string;
    /** Where new passes are added */
    storeUrl: string;
}>();

const { trans, transChoice } = useTrans();
const toast = useToast();
const timezone = userTimezone(useAuth().value.user);

const statusColors = {
    active: 'success',
    expired: 'warning',
    revoked: 'neutral',
} as const;

async function copy(pass: GuestPassData) {
    await navigator.clipboard.writeText(pass.password);
    toast.add({
        title: trans('dj.guest_passes.copied'),
        icon: 'i-lucide-check',
        color: 'success',
    });
}

// Adding or editing a pass
const formOpen = ref(false);
const editing = ref<GuestPassData | null>(null);
const form = useForm({
    label: '',
    password: '',
    regenerate_password: false,
    can_add: true,
    expires_on: '',
});

function openAdd() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    formOpen.value = true;
}

function openEdit(pass: GuestPassData) {
    editing.value = pass;
    form.clearErrors();
    form.label = pass.label;
    form.password = pass.password;
    form.regenerate_password = false;
    form.can_add = pass.can_add;
    form.expires_on = pass.expires_on ?? '';
    formOpen.value = true;
}

function save() {
    const pass = editing.value;

    form.transform((data) => ({
        label: data.label,
        password: data.regenerate_password ? null : data.password || null,
        can_add: data.can_add,
        expires_on: data.expires_on || null,
        ...(pass ? { regenerate_password: data.regenerate_password } : {}),
    })).submit(
        pass ? update(pass.id) : { url: props.storeUrl, method: 'post' },
        {
            preserveScroll: true,
            onSuccess: () => (formOpen.value = false),
        },
    );
}

function setRevoked(pass: GuestPassData, revoked: boolean) {
    router.put(
        update.url(pass.id),
        {
            label: pass.label,
            can_add: pass.can_add,
            expires_on: pass.expires_on,
            revoked,
        },
        { preserveScroll: true },
    );
}

const deleting = ref<GuestPassData | null>(null);

function remove() {
    if (!deleting.value) {
        return;
    }

    router.delete(destroy.url(deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <UPageCard :title="title" :description="description" variant="subtle">
        <ul v-if="passes.length" class="divide-default flex flex-col divide-y">
            <li
                v-for="pass in passes"
                :key="pass.id"
                class="flex flex-wrap items-center justify-between gap-3 py-3"
            >
                <div class="flex min-w-0 flex-col gap-1">
                    <span class="flex flex-wrap items-center gap-2">
                        <span class="text-highlighted font-medium">{{
                            pass.label
                        }}</span>
                        <UBadge
                            :color="statusColors[pass.status]"
                            variant="subtle"
                            size="sm"
                        >
                            {{ trans(`dj.guest_passes.status.${pass.status}`) }}
                        </UBadge>
                        <UBadge color="neutral" variant="outline" size="sm">
                            {{
                                pass.can_add
                                    ? trans('dj.guest_passes.can_add')
                                    : trans('dj.guest_passes.view_only')
                            }}
                        </UBadge>
                    </span>
                    <span class="flex items-center gap-1">
                        <code class="text-sm">{{ pass.password }}</code>
                        <UButton
                            size="xs"
                            color="neutral"
                            variant="ghost"
                            icon="i-lucide-copy"
                            :aria-label="trans('dj.guest_passes.copy')"
                            :title="trans('dj.guest_passes.copy')"
                            @click="copy(pass)"
                        />
                    </span>
                    <span class="text-muted text-xs">
                        {{
                            transChoice(
                                'dj.guest_passes.songs',
                                pass.items_count,
                            )
                        }}
                        ·
                        {{
                            pass.last_used_at
                                ? trans('dj.guest_passes.last_used', {
                                      date: formatDateTime(
                                          pass.last_used_at,
                                          timezone,
                                      ),
                                  })
                                : trans('dj.guest_passes.never_used')
                        }}
                        <template v-if="pass.expires_on">
                            ·
                            {{
                                trans('dj.guest_passes.expires', {
                                    date: pass.expires_on,
                                })
                            }}
                        </template>
                    </span>
                </div>
                <div class="flex flex-wrap gap-1">
                    <UButton
                        size="xs"
                        color="neutral"
                        variant="outline"
                        icon="i-lucide-pencil"
                        :label="trans('common.actions.edit')"
                        @click="openEdit(pass)"
                    />
                    <UButton
                        size="xs"
                        color="neutral"
                        variant="outline"
                        :icon="
                            pass.status === 'revoked'
                                ? 'i-lucide-rotate-ccw'
                                : 'i-lucide-ban'
                        "
                        :label="
                            pass.status === 'revoked'
                                ? trans('dj.guest_passes.restore')
                                : trans('dj.guest_passes.revoke')
                        "
                        @click="setRevoked(pass, pass.status !== 'revoked')"
                    />
                    <UButton
                        size="xs"
                        color="error"
                        variant="ghost"
                        icon="i-lucide-trash"
                        :aria-label="trans('common.actions.delete')"
                        @click="deleting = pass"
                    />
                </div>
            </li>
        </ul>
        <p v-else class="text-muted text-sm">
            {{ trans('dj.guest_passes.empty') }}
        </p>

        <UButton
            icon="i-lucide-key-round"
            color="neutral"
            variant="outline"
            class="w-fit"
            :label="trans('dj.guest_passes.add')"
            @click="openAdd"
        />

        <UModal
            v-model:open="formOpen"
            :title="
                editing
                    ? trans('dj.guest_passes.edit_title')
                    : trans('dj.guest_passes.add_title')
            "
        >
            <template #body>
                <form class="flex flex-col gap-4" @submit.prevent="save">
                    <UFormField
                        :label="trans('dj.guest_passes.fields.label')"
                        :error="form.errors.label"
                        required
                    >
                        <UInput
                            v-model="form.label"
                            class="w-full"
                            :placeholder="
                                trans(
                                    'dj.guest_passes.fields.label_placeholder',
                                )
                            "
                            autofocus
                        />
                    </UFormField>
                    <UFormField
                        :label="trans('dj.guest_passes.fields.password')"
                        :error="form.errors.password"
                    >
                        <UInput
                            v-model="form.password"
                            class="w-full font-mono"
                            :disabled="form.regenerate_password"
                            :placeholder="
                                trans('dj.guest_passes.password_placeholder')
                            "
                        />
                    </UFormField>
                    <UCheckbox
                        v-if="editing"
                        v-model="form.regenerate_password"
                        :label="trans('dj.guest_passes.regenerate')"
                    />
                    <USwitch
                        v-model="form.can_add"
                        :label="trans('dj.guest_passes.fields.can_add')"
                        :description="
                            trans('dj.guest_passes.fields.can_add_help')
                        "
                    />
                    <UFormField
                        :label="trans('dj.guest_passes.fields.expires_on')"
                        :help="trans('dj.guest_passes.fields.expires_on_help')"
                        :error="form.errors.expires_on"
                    >
                        <UInput v-model="form.expires_on" type="date" />
                    </UFormField>
                </form>
            </template>
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="formOpen = false"
                    />
                    <UButton
                        :loading="form.processing"
                        :label="trans('common.actions.save')"
                        @click="save"
                    />
                </div>
            </template>
        </UModal>

        <UModal
            :open="deleting !== null"
            :title="trans('common.actions.delete')"
            @update:open="(open: boolean) => !open && (deleting = null)"
        >
            <template #body>
                <p class="text-sm">
                    {{
                        trans('dj.guest_passes.confirm_delete', {
                            label: deleting?.label ?? '',
                        })
                    }}
                </p>
            </template>
            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="ghost"
                        :label="trans('common.actions.cancel')"
                        @click="deleting = null"
                    />
                    <UButton
                        color="error"
                        :label="trans('common.actions.delete')"
                        @click="remove"
                    />
                </div>
            </template>
        </UModal>
    </UPageCard>
</template>

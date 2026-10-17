<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/Central/Admin/TenantDomainController';
import type { TenantData, TenantDomainData } from '@/types';

const props = defineProps<{
    tenant: TenantData;
}>();

const { trans } = useTrans();

const addForm = useForm({ domain: '' });
const editForm = useForm({ domain: '' });
const removeForm = useForm({});

/** The domain being edited in place, if any */
const editingId = ref<number | null>(null);
const removing = ref<TenantDomainData | null>(null);
const removeModalOpen = computed({
    get: () => removing.value !== null,
    set: (open: boolean) => {
        if (!open) {
            removing.value = null;
        }
    },
});

function addDomain() {
    addForm.submit(store({ tenant: props.tenant.id }), {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            removeForm.clearErrors();
        },
    });
}

function startEditing(domain: TenantDomainData) {
    editingId.value = domain.id;
    editForm.domain = domain.domain;
    editForm.clearErrors();
}

function saveDomain(domain: TenantDomainData) {
    editForm.submit(update({ tenant: props.tenant.id, domain: domain.id }), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            removeForm.clearErrors();
        },
    });
}

function removeDomain() {
    if (!removing.value) {
        return;
    }

    removeForm.submit(
        destroy({ tenant: props.tenant.id, domain: removing.value.id }),
        {
            preserveScroll: true,
            onFinish: () => (removing.value = null),
        },
    );
}
</script>

<template>
    <UCard>
        <template #header>
            <h2 class="font-semibold">
                {{ trans('tenants.show.domains') }}
            </h2>
        </template>

        <div class="flex flex-col gap-6">
            <UAlert
                v-if="(removeForm.errors as Record<string, string>).domain"
                color="error"
                variant="subtle"
                icon="i-lucide-circle-alert"
                :description="
                    (removeForm.errors as Record<string, string>).domain
                "
            />

            <ul class="divide-default flex flex-col divide-y">
                <li
                    v-for="domain in tenant.domains"
                    :key="domain.id"
                    class="py-2 first:pt-0"
                >
                    <form
                        v-if="editingId === domain.id"
                        class="flex items-start gap-2"
                        @submit.prevent="saveDomain(domain)"
                    >
                        <UFormField
                            :error="editForm.errors.domain"
                            class="flex-1"
                        >
                            <UInput
                                v-model="editForm.domain"
                                class="w-full font-mono"
                                autofocus
                            />
                        </UFormField>
                        <UButton
                            type="submit"
                            color="primary"
                            :loading="editForm.processing"
                            :label="trans('tenants.domains.save')"
                        />
                        <UButton
                            color="neutral"
                            variant="ghost"
                            :label="trans('tenants.domains.cancel')"
                            @click="editingId = null"
                        />
                    </form>

                    <div v-else class="flex items-center justify-between gap-4">
                        <span class="font-mono text-sm">{{
                            domain.domain
                        }}</span>
                        <div class="flex items-center gap-1">
                            <UButton
                                v-if="domain.admin_url"
                                :href="domain.admin_url"
                                target="_blank"
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                trailing-icon="i-lucide-external-link"
                                :label="trans('tenants.show.open_admin')"
                            />
                            <UButton
                                color="neutral"
                                variant="ghost"
                                size="sm"
                                icon="i-lucide-pencil"
                                :aria-label="trans('tenants.domains.edit')"
                                @click="startEditing(domain)"
                            />
                            <UButton
                                color="error"
                                variant="ghost"
                                size="sm"
                                icon="i-lucide-trash"
                                :aria-label="trans('tenants.domains.remove')"
                                @click="removing = domain"
                            />
                        </div>
                    </div>
                </li>
            </ul>

            <form class="flex items-start gap-2" @submit.prevent="addDomain">
                <UFormField
                    :help="trans('tenants.domains.help')"
                    :error="addForm.errors.domain"
                    class="flex-1"
                >
                    <UInput
                        v-model="addForm.domain"
                        :placeholder="trans('tenants.domains.placeholder')"
                        class="w-full font-mono"
                    />
                </UFormField>
                <UButton
                    type="submit"
                    color="neutral"
                    variant="outline"
                    icon="i-lucide-plus"
                    :loading="addForm.processing"
                    :label="trans('tenants.domains.add')"
                />
            </form>
        </div>

        <UModal
            v-model:open="removeModalOpen"
            :title="trans('tenants.domains.remove')"
            :description="
                trans('tenants.domains.confirm_remove', {
                    domain: removing?.domain ?? '',
                })
            "
        >
            <template #body>
                <div class="flex justify-end gap-2">
                    <UButton
                        color="neutral"
                        variant="subtle"
                        :label="trans('common.actions.cancel')"
                        @click="removing = null"
                    />
                    <UButton
                        color="error"
                        :loading="removeForm.processing"
                        :label="trans('tenants.domains.remove')"
                        @click="removeDomain"
                    />
                </div>
            </template>
        </UModal>
    </UCard>
</template>

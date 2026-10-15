<?php

namespace App\Policies\Crm;

use App\Models\Crm\Form;
use App\Models\User;
use App\Services\Authorisation\Enums\CrmPermission;

class FormPolicy
{
    /**
     * Whether the user can list forms.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CrmPermission::ViewForms);
    }

    /**
     * Whether the user can view the given form.
     */
    public function view(User $user, Form $form): bool
    {
        return $user->can(CrmPermission::ViewForms);
    }

    /**
     * Whether the user can create forms.
     */
    public function create(User $user): bool
    {
        return $user->can(CrmPermission::ManageForms);
    }

    /**
     * Whether the user can update the given form.
     */
    public function update(User $user, Form $form): bool
    {
        return $user->can(CrmPermission::ManageForms);
    }

    /**
     * Whether the user can delete the given form (a soft delete).
     */
    public function delete(User $user, Form $form): bool
    {
        return $user->can(CrmPermission::ManageForms);
    }
}

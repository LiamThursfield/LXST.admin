<?php

namespace App\Policies\Crm;

use App\Models\Crm\Contact;
use App\Models\User;
use App\Services\Authorisation\Enums\CrmPermission;

class ContactPolicy
{
    /**
     * Whether the user can list contacts.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CrmPermission::ViewContacts);
    }

    /**
     * Whether the user can view the given contact.
     */
    public function view(User $user, Contact $contact): bool
    {
        return $user->can(CrmPermission::ViewContacts);
    }

    /**
     * Whether the user can create contacts.
     */
    public function create(User $user): bool
    {
        return $user->can(CrmPermission::ManageContacts);
    }

    /**
     * Whether the user can update the given contact, including marking it as spam.
     */
    public function update(User $user, Contact $contact): bool
    {
        return $user->can(CrmPermission::ManageContacts);
    }

    /**
     * Whether the user can delete the given contact (a soft delete).
     */
    public function delete(User $user, Contact $contact): bool
    {
        return $user->can(CrmPermission::ManageContacts);
    }

    /**
     * Whether the user can restore the given deleted contact.
     */
    public function restore(User $user, Contact $contact): bool
    {
        return $user->can(CrmPermission::ManageContacts);
    }
}

<?php

namespace App\Policies\Crm;

use App\Models\Crm\FormSubmission;
use App\Models\User;
use App\Services\Authorisation\Enums\CrmPermission;

class FormSubmissionPolicy
{
    /**
     * Whether the user can list form submissions.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CrmPermission::ViewFormSubmissions);
    }

    /**
     * Whether the user can view the given form submission.
     */
    public function view(User $user, FormSubmission $formSubmission): bool
    {
        return $user->can(CrmPermission::ViewFormSubmissions);
    }

    /**
     * Whether the user can mark the given form submission as spam or not spam.
     * Submissions are otherwise read-only.
     */
    public function update(User $user, FormSubmission $formSubmission): bool
    {
        return $user->can(CrmPermission::ManageFormSubmissions);
    }
}

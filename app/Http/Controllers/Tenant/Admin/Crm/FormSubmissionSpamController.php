<?php

namespace App\Http\Controllers\Tenant\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a single form submission as spam (store) or not spam (destroy)
 */
class FormSubmissionSpamController extends Controller
{
    public function store(FormSubmission $formSubmission): RedirectResponse
    {
        Gate::authorize('update', $formSubmission);

        $formSubmission->update(['is_spam' => true]);

        return redirect()->back()->with('success', __('crm.submissions.flash.marked_spam'));
    }

    public function destroy(FormSubmission $formSubmission): RedirectResponse
    {
        Gate::authorize('update', $formSubmission);

        $formSubmission->update(['is_spam' => false]);

        return redirect()->back()->with('success', __('crm.submissions.flash.marked_not_spam'));
    }
}

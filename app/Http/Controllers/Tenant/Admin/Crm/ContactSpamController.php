<?php

namespace App\Http\Controllers\Tenant\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Marks a contact as spam (store) or not spam (destroy)
 */
class ContactSpamController extends Controller
{
    public function store(Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $contact->update(['is_spam' => true]);

        return redirect()->back()->with('success', __('crm.contacts.flash.marked_spam'));
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('update', $contact);

        $contact->update(['is_spam' => false]);

        return redirect()->back()->with('success', __('crm.contacts.flash.marked_not_spam'));
    }
}

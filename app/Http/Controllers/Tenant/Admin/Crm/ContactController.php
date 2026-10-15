<?php

namespace App\Http\Controllers\Tenant\Admin\Crm;

use App\Http\Controllers\Controller;
use App\Http\Queries\Tenant\Admin\Crm\ContactIndexQuery;
use App\Http\Requests\Tenant\Admin\Crm\ContactIndexRequest;
use App\Http\Requests\Tenant\Admin\Crm\ContactStoreRequest;
use App\Http\Requests\Tenant\Admin\Crm\ContactUpdateRequest;
use App\Http\Resources\Tenant\Admin\Crm\ContactResource;
use App\Models\Crm\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(ContactIndexRequest $request, ContactIndexQuery $query): Response
    {
        return Inertia::render('admin/crm/contact/Index', [
            'filter' => function () use ($request) {
                /** @var array<mixed>|null $validated */
                $validated = $request->validated('filter');
                if ($validated == null) {
                    return null;
                }

                return collect($validated)->filter(fn (mixed $value) => $value !== null && $value !== '')->toArray();
            },
            'columns' => [
                ['accessorKey' => 'name_with_title', 'header' => __('crm.contacts.columns.name')],
                ['accessorKey' => 'email', 'header' => __('crm.contacts.columns.email')],
                ['accessorKey' => 'telephone', 'header' => __('crm.contacts.columns.telephone')],
                ['accessorKey' => 'is_spam', 'header' => __('crm.contacts.columns.status')],
                ['accessorKey' => 'actions', 'header' => ''],
            ],
            'contacts' => ContactResource::collection($query->handle($request)),
            'canCreate' => $request->user()?->can('create', Contact::class) ?? false,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Contact::class);

        return Inertia::render('admin/crm/contact/Create');
    }

    public function store(ContactStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $contact = Contact::create($request->contactAttributes());

            $contact->addresses()->createMany($request->addressesAttributes());
        });

        return redirect()->route('admin.crm.contacts.index')
            ->with('success', __('crm.contacts.flash.created'));
    }

    public function show(Contact $contact): Response
    {
        Gate::authorize('view', $contact);

        return Inertia::render('admin/crm/contact/Show', [
            'contact' => new ContactResource($contact->load('addresses')),
        ]);
    }

    public function edit(Contact $contact): Response
    {
        Gate::authorize('update', $contact);

        return Inertia::render('admin/crm/contact/Edit', [
            'contact' => new ContactResource($contact->load('addresses')),
        ]);
    }

    public function update(ContactUpdateRequest $request, Contact $contact): RedirectResponse
    {
        DB::transaction(function () use ($request, $contact) {
            $contact->update($request->contactAttributes());

            // The form always sends the full list, so replace the contact's addresses with it
            $contact->addresses()->delete();
            $contact->addresses()->createMany($request->addressesAttributes());
        });

        return redirect()->route('admin.crm.contacts.index')
            ->with('success', __('crm.contacts.flash.updated'));
    }

    /**
     * Soft deletes the contact; its addresses are kept so it can be restored
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        $contact->delete();

        return redirect()->back()->with('success', __('crm.contacts.flash.deleted'));
    }

    public function restore(Contact $contact): RedirectResponse
    {
        Gate::authorize('restore', $contact);

        $contact->restore();

        return redirect()->back()->with('success', __('crm.contacts.flash.restored'));
    }
}

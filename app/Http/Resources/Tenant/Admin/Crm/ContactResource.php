<?php

namespace App\Http\Resources\Tenant\Admin\Crm;

use App\Http\Resources\Traits\HasActions;
use App\Models\Crm\Address;
use App\Models\Crm\Contact;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var Contact $contact */
        $contact = $this->resource;
        $user = $request->user();

        // A deleted contact can only be restored
        if ($contact->trashed()) {
            return $user?->can('restore', $contact)
                ? [Action::make(__('crm.contacts.actions.restore'), route('admin.crm.contacts.restore', $contact))
                    ->icon('i-lucide-rotate-ccw')
                    ->method('PATCH')]
                : [];
        }

        $actions = [];

        if ($user?->can('view', $contact)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.crm.contacts.show', $contact))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $contact)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.crm.contacts.edit', $contact))
                ->icon('i-lucide-pencil');

            $actions[] = $contact->is_spam
                ? Action::make(__('crm.contacts.actions.not_spam'), route('admin.crm.contacts.spam.destroy', $contact))
                    ->icon('i-lucide-shield-check')
                    ->method('DELETE')
                : Action::make(__('crm.contacts.actions.mark_spam'), route('admin.crm.contacts.spam.store', $contact))
                    ->icon('i-lucide-shield-alert')
                    ->method('POST');
        }

        if ($user?->can('delete', $contact)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.crm.contacts.destroy', $contact))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('crm.contacts.confirm_delete', ['email' => $contact->email]));
        }

        return $actions;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Contact $contact */
        $contact = $this->resource;

        return $this->appendActions($request, [
            'id' => $contact->id,
            'title' => $contact->title,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'name' => $contact->name,
            'name_with_title' => $contact->name_with_title,
            'email' => $contact->email,
            'telephone' => $contact->telephone,
            'is_spam' => $contact->is_spam,
            'marketing_email' => $contact->marketing_email,
            'marketing_sms' => $contact->marketing_sms,
            'marketing_telephone' => $contact->marketing_telephone,
            'created_at' => $contact->created_at?->toIso8601String(),
            'deleted_at' => $contact->deleted_at?->toIso8601String(),
            'addresses' => $this->whenLoaded('addresses', fn () => $contact->addresses
                ->map(fn (Address $address) => $address->only(['id', ...Address::FIELDS]))
                ->values()),
        ]);
    }
}

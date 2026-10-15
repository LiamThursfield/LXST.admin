<?php

namespace App\Http\Resources\Tenant\Admin\Crm;

use App\Http\Resources\Traits\HasActions;
use App\Models\Crm\Address;
use App\Models\Crm\OrganisationUnit;
use App\Support\UI\Action;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganisationUnitResource extends JsonResource
{
    use HasActions;

    /**
     * @return Action[]
     */
    protected function getActions(Request $request): array
    {
        /** @var OrganisationUnit $organisationUnit */
        $organisationUnit = $this->resource;
        $user = $request->user();
        $actions = [];

        if ($user?->can('view', $organisationUnit)) {
            $actions[] = Action::make(__('common.actions.view'), route('admin.crm.organisation-units.show', $organisationUnit))
                ->icon('i-lucide-eye');
        }

        if ($user?->can('update', $organisationUnit)) {
            $actions[] = Action::make(__('common.actions.edit'), route('admin.crm.organisation-units.edit', $organisationUnit))
                ->icon('i-lucide-pencil');
        }

        // Units that still have children cannot be deleted
        if ($user?->can('delete', $organisationUnit) && ! $this->hasChildren($organisationUnit)) {
            $actions[] = Action::make(__('common.actions.delete'), route('admin.crm.organisation-units.destroy', $organisationUnit))
                ->icon('i-lucide-trash')
                ->method('DELETE')
                ->requireConfirmation(__('crm.organisation_units.confirm_delete', ['name' => $organisationUnit->name]));
        }

        return $actions;
    }

    /**
     * Uses the children_count from the index query when it was loaded
     */
    protected function hasChildren(OrganisationUnit $organisationUnit): bool
    {
        if (isset($organisationUnit->children_count)) {
            return $organisationUnit->children_count > 0;
        }

        return $organisationUnit->children()->exists();
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OrganisationUnit $organisationUnit */
        $organisationUnit = $this->resource;

        return $this->appendActions($request, [
            'id' => $organisationUnit->id,
            'type' => $organisationUnit->type->value,
            'type_label' => $organisationUnit->type->label(),
            'name' => $organisationUnit->name,
            'slug' => $organisationUnit->slug,
            'email' => $organisationUnit->email,
            'telephone' => $organisationUnit->telephone,
            'mobile_phone' => $organisationUnit->mobile_phone,
            'socials' => (object) ($organisationUnit->socials ?? []),
            'notification_emails' => $organisationUnit->notification_emails ?? [],
            'parent_id' => $organisationUnit->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $organisationUnit->parent === null ? null : [
                'id' => $organisationUnit->parent->id,
                'name' => $organisationUnit->parent->name,
            ]),
            'address' => $this->whenLoaded('address', fn () => $organisationUnit->address?->only(['id', ...Address::FIELDS])),
        ]);
    }
}

<?php

namespace App\Services\Cms;

use App\Enums\TenantModule;
use App\Models\Cms\Menu;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;

/**
 * The choices content fields offer (menus, CRM forms and organisation units), for the page and
 * layout editors
 */
class ContentFieldOptions
{
    /**
     * @return array<int, array{value: int, label: string}>
     */
    public function menus(): array
    {
        return Menu::query()->orderBy('name')->get(['id', 'name'])
            ->map(fn (Menu $menu) => ['value' => $menu->id, 'label' => $menu->name])
            ->values()
            ->all();
    }

    /**
     * The CRM forms and organisation units, or null when the tenant doesn't have the CRM module
     * (CRM fields are then hidden, and keep their values)
     *
     * @return array{forms: array<int, array{value: int, label: string}>, organisationUnits: array<int, array{value: int, label: string, type: string}>}|null
     */
    public function crm(): ?array
    {
        if (! (tenant()?->hasModule(TenantModule::Crm) ?? false)) {
            return null;
        }

        return [
            'forms' => Form::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Form $form) => ['value' => $form->id, 'label' => $form->name])
                ->values()
                ->all(),
            'organisationUnits' => OrganisationUnit::query()->orderBy('name')->get(['id', 'name', 'type'])
                ->map(fn (OrganisationUnit $unit) => [
                    'value' => $unit->id,
                    'label' => "{$unit->name} ({$unit->type->label()})",
                    'type' => $unit->type->value,
                ])
                ->values()
                ->all(),
        ];
    }
}

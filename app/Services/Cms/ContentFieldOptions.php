<?php

namespace App\Services\Cms;

use App\Enums\TenantModule;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\SavedComponent;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;
use App\Models\User;

/**
 * The choices content fields offer (menus, pages, CRM forms, organisation units and saved
 * components), for the page and layout editors
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
     * The pages menu items and link fields can go to, in URL order
     *
     * @return array<int, array{value: int, label: string, url: string|null}>
     */
    public function pages(): array
    {
        return Page::query()
            ->with('url')
            ->get()
            ->sortBy(fn (Page $page) => $page->url->url_full ?? '')
            ->map(fn (Page $page) => [
                'value' => $page->id,
                'label' => $page->name.' ('.($page->url->url_full ?? '?').')',
                'url' => $page->url?->url_full,
            ])
            ->values()
            ->all();
    }

    /**
     * The saved components component fields can use, with their content to show read-only, and
     * whether the user can make and edit them
     *
     * @return array{items: array<int, array{id: int, template_id: int, name: string, content: object}>, canManage: bool}
     */
    public function savedComponents(?User $user): array
    {
        return [
            'items' => SavedComponent::query()->with(['template.fields', 'content'])->orderBy('name')->get()
                ->map(fn (SavedComponent $savedComponent) => $this->savedComponent($savedComponent))
                ->values()
                ->all(),
            'canManage' => $user?->can('create', SavedComponent::class) ?? false,
        ];
    }

    /**
     * @return array{id: int, template_id: int, name: string, content: object}
     */
    public function savedComponent(SavedComponent $savedComponent): array
    {
        return [
            'id' => $savedComponent->id,
            'template_id' => $savedComponent->template_id,
            'name' => $savedComponent->name,
            'content' => (object) $savedComponent->contentValues(),
        ];
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

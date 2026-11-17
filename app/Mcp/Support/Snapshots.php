<?php

namespace App\Mcp\Support;

use App\Data\Cms\MenuItemData;
use App\Models\Cms\Layout;
use App\Models\Cms\Menu;
use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\Cms\TemplateSection;
use App\Models\Crm\Form;
use App\Models\Crm\FormField;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Records as the MCP tools show them, in the same shape the admin's form requests take, so a
 * record can be read, changed and sent back. Also used for the audit log's before and after.
 */
class Snapshots
{
    /**
     * @return array<string, mixed>
     */
    public static function page(Page $page, User $user): array
    {
        $page->loadMissing('url', 'metadata', 'template');
        $url = $page->url;

        return [
            'id' => $page->id,
            'name' => $page->name,
            'slug' => $page->slug,
            'url' => $url?->url_full,
            'parent_id' => $page->parent_id,
            'template_id' => $page->template_id,
            'template' => $page->template->slug,
            'layout_id' => $page->layout_id,
            'status' => $url?->status()->value,
            'is_enabled' => $url->is_enabled ?? false,
            // On the token user's clock, as the admin's page editor takes them
            'published_at' => self::localTime($url?->published_at, $user),
            'expired_at' => self::localTime($url?->expired_at, $user),
            'metadata' => collect(Metadata::FIELDS)
                ->mapWithKeys(fn (string $field) => [$field => $page->metadata?->{$field}])
                ->all(),
            'content' => $page->contentValues(),
        ];
    }

    /**
     * The page as the admin's page form would send it
     *
     * @return array<string, mixed>
     */
    public static function pageInput(Page $page, User $user): array
    {
        $snapshot = self::page($page, $user);

        return [
            'template_id' => $snapshot['template_id'],
            'layout_id' => $snapshot['layout_id'],
            'parent_id' => $snapshot['parent_id'],
            'name' => $snapshot['name'],
            'slug' => $snapshot['slug'],
            'is_enabled' => $snapshot['is_enabled'],
            'published_at' => $snapshot['published_at'],
            'expired_at' => $snapshot['expired_at'],
            'metadata' => $snapshot['metadata'],
            'content' => $snapshot['content'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function layout(Layout $layout): array
    {
        $layout->loadMissing('template');

        return [
            'id' => $layout->id,
            'name' => $layout->name,
            'slug' => $layout->slug,
            'template_id' => $layout->template_id,
            'template' => $layout->template->slug,
            'pages_count' => $layout->pages()->count(),
            'content' => $layout->contentValues(),
        ];
    }

    /**
     * The layout as the admin's layout form would send it
     *
     * @return array<string, mixed>
     */
    public static function layoutInput(Layout $layout): array
    {
        return [
            'template_id' => $layout->template_id,
            'name' => $layout->name,
            'slug' => $layout->slug,
            'content' => $layout->contentValues(),
        ];
    }

    /**
     * The template with its fields: those in no section under `fields`, the rest in their section
     *
     * @return array<string, mixed>
     */
    public static function template(Template $template): array
    {
        $template->loadMissing('fields', 'sections');

        return [
            'id' => $template->id,
            'type' => $template->type->value,
            'name' => $template->name,
            'slug' => $template->slug,
            'description' => $template->description,
            'fields' => $template->fields
                ->whereNull('section_id')
                ->map(fn (TemplateField $field) => self::templateField($field))
                ->values()
                ->all(),
            'sections' => $template->sections
                ->map(fn (TemplateSection $section) => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'description' => $section->description,
                    'is_collapsed' => $section->is_collapsed,
                    'fields' => $template->fields
                        ->where('section_id', $section->id)
                        ->map(fn (TemplateField $field) => self::templateField($field))
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function templateField(TemplateField $field): array
    {
        return [
            'id' => $field->id,
            'type' => $field->type->value,
            'name' => $field->name,
            'slug' => $field->slug,
            'description' => $field->description,
            'is_required' => $field->is_required,
            'settings' => array_filter($field->settings?->toArray() ?? [], fn (mixed $value) => $value !== null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function menu(Menu $menu): array
    {
        return [
            'id' => $menu->id,
            'name' => $menu->name,
            'slug' => $menu->slug,
            'menu_items' => self::menuItems($menu->menu_items),
        ];
    }

    /**
     * @param  iterable<MenuItemData>|null  $items
     * @return list<array<string, mixed>>
     */
    protected static function menuItems(?iterable $items): array
    {
        $list = [];

        foreach ($items ?? [] as $item) {
            $list[] = [
                'label' => $item->label,
                'link_type' => $item->link_type,
                'page_id' => $item->page_id,
                'url' => $item->url,
                'target' => $item->target,
                'rel' => $item->rel,
                'custom_class' => $item->custom_class,
                'children' => self::menuItems($item->children),
            ];
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public static function form(Form $form): array
    {
        $form->loadMissing('fields');

        return [
            'id' => $form->id,
            'name' => $form->name,
            'slug' => $form->slug,
            'email_recipients' => $form->email_recipients ?? [],
            'marketing_email' => (bool) $form->marketing_email,
            'marketing_sms' => (bool) $form->marketing_sms,
            'marketing_telephone' => (bool) $form->marketing_telephone,
            'captcha_enabled' => (bool) $form->captcha_enabled,
            'submit_button_text' => $form->submit_button_text,
            'redirect_url' => $form->redirect_url,
            'success_message' => $form->success_message,
            'fields' => $form->fields
                ->map(fn (FormField $field) => [
                    'id' => $field->id,
                    'type' => $field->type->value,
                    'name' => $field->name,
                    'slug' => $field->slug,
                    'is_required' => $field->is_required,
                    'settings' => array_filter($field->settings?->toArray() ?? [], fn (mixed $value) => $value !== null),
                ])
                ->values()
                ->all(),
        ];
    }

    protected static function localTime(?CarbonInterface $time, User $user): ?string
    {
        return $time?->copy()->setTimezone($user->localTimezone())->format('Y-m-d\TH:i');
    }
}

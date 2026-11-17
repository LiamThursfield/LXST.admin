<?php

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Api\ContentScope;
use App\Enums\Api\McpOutcome;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\McpAuditLog;
use App\Services\Cms\Api\ContentVersion;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = mcpUser($this);
    $this->token = mcpToken($this->user, [ApiTokenAbility::McpCmsRead, ApiTokenAbility::McpCmsStructure]);
});

test('a template is created with its sections and fields, and read back in the same shape', function () {
    $result = mcpCall($this, $this->token, 'create_template', [
        'type' => 'page',
        'name' => 'Home',
        'fields' => [
            ['type' => 'text', 'name' => 'Hero heading', 'is_required' => true],
            ['type' => 'select', 'name' => 'Theme', 'settings' => ['options' => [['label' => 'Dark', 'value' => 'dark'], ['label' => 'Light', 'value' => 'light']], 'default' => 'dark']],
        ],
        'sections' => [
            ['name' => 'Intro', 'fields' => [['type' => 'wysiwyg', 'name' => 'Intro text', 'settings' => ['width' => 'half']]]],
        ],
    ]);

    $template = Template::sole();

    expect($result['isError'])->toBeFalse()
        ->and($template)->slug->toBe('home')->type->toBe(TemplateType::Page)
        ->and($template->fields->pluck('slug')->all())->toBe(['hero_heading', 'theme', 'intro_text']);

    $read = mcpCall($this, $this->token, 'get_template', ['slug' => 'home'])['structuredContent']['template'];

    expect($read['fields'])->toHaveCount(2)
        ->and($read['sections'][0]['name'])->toBe('Intro')
        ->and($read['sections'][0]['fields'][0])->slug->toBe('intro_text')->settings->toBe(['width' => 'half']);
});

test('updating a template matches fields by slug, and refuses to remove any', function () {
    mcpCall($this, $this->token, 'create_template', ['type' => 'page', 'name' => 'Contact', 'fields' => [['type' => 'text', 'name' => 'Heading']]]);
    $template = Template::sole();

    $added = mcpCall($this, $this->token, 'update_template', [
        'id' => $template->id,
        'fields' => [['type' => 'textarea', 'name' => 'Intro'], ['type' => 'text', 'name' => 'Title', 'slug' => 'heading']],
    ]);

    expect($added['isError'])->toBeFalse()
        ->and($template->fresh()->fields->map(fn ($field) => [$field->slug, $field->name])->all())->toBe([['intro', 'Intro'], ['heading', 'Title']]);

    // "Heading" is kept, matched by the slug made from its name, so only "intro" would be removed
    $removed = mcpCall($this, $this->token, 'update_template', ['id' => $template->id, 'fields' => [['type' => 'text', 'name' => 'Heading']]]);

    expect($removed['isError'])->toBeTrue()
        ->and($removed['content'][0]['text'])->toContain('intro')
        ->and($removed['content'][0]['text'])->not->toContain('heading')
        ->and($template->fresh()->fields)->toHaveCount(2)
        ->and(McpAuditLog::query()->latest('id')->first()->outcome)->toBe(McpOutcome::Denied);
});

test('a header menu is created with a call-to-action item, and changes bump the API\'s content version', function () {
    $page = Page::factory()->create(['slug' => 'contact']);
    $version = ContentVersion::for(ContentScope::Cms)->current();

    $result = mcpCall($this, $this->token, 'create_menu', [
        'name' => 'Header',
        'menu_items' => [
            ['label' => 'Work', 'link_type' => 'url', 'url' => '/work'],
            ['label' => 'Get in touch', 'link_type' => 'page', 'page_id' => $page->id, 'custom_class' => 'cta'],
        ],
    ]);

    $menu = Menu::sole();

    expect($result['isError'])->toBeFalse()
        ->and($menu->slug)->toBe('header')
        ->and($menu->menu_items[1]->custom_class)->toBe('cta')
        ->and(ContentVersion::for(ContentScope::Cms)->current())->not->toBe($version);

    mcpCall($this, $this->token, 'update_menu', ['id' => $menu->id, 'name' => 'Main navigation']);

    expect($menu->fresh())->name->toBe('Main navigation')->slug->toBe('header')
        ->and($menu->fresh()->menu_items)->toHaveCount(2);
});

test('unsafe menu links are refused, as in the admin', function () {
    $result = mcpCall($this, $this->token, 'create_menu', ['name' => 'Footer', 'menu_items' => [['label' => 'Bad', 'link_type' => 'url', 'url' => 'javascript:alert(1)']]]);

    expect($result['isError'])->toBeTrue()
        ->and(Menu::count())->toBe(0);
});

test('a token can only make so many changes a minute', function () {
    config(['api.mcp.writes_per_minute' => 1]);

    expect(mcpCall($this, $this->token, 'create_menu', ['name' => 'Header'])['isError'])->toBeFalse();

    $limited = mcpCall($this, $this->token, 'create_menu', ['name' => 'Footer']);

    expect($limited['isError'])->toBeTrue()
        ->and(Menu::count())->toBe(1)
        ->and(McpAuditLog::query()->latest('id')->first()->outcome)->toBe(McpOutcome::RateLimited);

    // Reading isn't limited
    expect(mcpCall($this, $this->token, 'list_menus')['isError'])->toBeFalse();
});

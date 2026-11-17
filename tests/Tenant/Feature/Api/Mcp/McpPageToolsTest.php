<?php

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Api\McpOutcome;
use App\Enums\Cms\TemplateFieldType;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\McpAuditLog;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = mcpUser($this);
    $this->token = mcpToken($this->user, [ApiTokenAbility::McpCmsRead, ApiTokenAbility::McpCmsContent]);

    $this->template = Template::factory()->create(['slug' => 'service']);
    TemplateField::factory()->for($this->template)->create(['name' => 'Heading', 'slug' => 'heading', 'is_required' => true, 'order' => 0]);
    TemplateField::factory()->for($this->template)->ofType(TemplateFieldType::Wysiwyg)->create(['slug' => 'body', 'order' => 1]);
    $this->layout = Layout::factory()->create();
});

/**
 * A live page using the test template
 */
function livePage(mixed $test, array $attributes = []): Page
{
    $page = Page::factory()->create(['template_id' => $test->template->id, 'layout_id' => $test->layout->id, ...$attributes]);
    $page->syncContent(['heading' => 'Live heading']);

    return $page;
}

test('a page is created as a draft, with rich text sent as HTML', function () {
    $result = mcpCall($this, $this->token, 'create_page', [
        'name' => 'Web design',
        'slug' => 'web-design',
        'template_id' => $this->template->id,
        'layout_id' => $this->layout->id,
        // Ignored: publishing needs a person, or mcp:cms:publish
        'is_enabled' => true,
        'metadata' => ['title' => 'Web design | LXST'],
        'content' => [
            'heading' => 'Websites that work',
            'body' => '<h2>Fast</h2><p>Built <strong>right</strong>.</p><script>alert(1)</script>',
        ],
    ]);

    $page = Page::sole();

    expect($result['isError'])->toBeFalse()
        ->and($result['structuredContent']['page'])
        ->id->toBe($page->id)
        ->url->toBe('/web-design')
        ->status->toBe('draft')
        ->and($page->url->is_enabled)->toBeFalse()
        ->and($page->metadata->title)->toBe('Web design | LXST')
        ->and($page->contentValues()['heading'])->toBe('Websites that work')
        ->and($page->contentValues()['body']['html'])->toBe('<h2>Fast</h2><p>Built <strong>right</strong>.</p>');

    $log = McpAuditLog::sole();

    expect($log)
        ->tool->toBe('create_page')
        ->outcome->toBe(McpOutcome::Success)
        ->target_type->toBe('page')
        ->target_id->toBe($page->id)
        ->and($log->changes['content.heading'])->toEqual(['before' => null, 'after' => 'Websites that work']);
});

test('invalid pages come back with the admin\'s validation messages and nothing is saved', function () {
    $result = mcpCall($this, $this->token, 'create_page', [
        'name' => 'About',
        'slug' => 'About Us!',
        'template_id' => $this->template->id,
        'layout_id' => $this->layout->id,
        'content' => [],
    ]);

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('Heading')
        ->and(Page::count())->toBe(0)
        ->and(McpAuditLog::sole()->outcome)->toBe(McpOutcome::Invalid);
});

test('updating a draft only changes what\'s sent, merging content field by field', function () {
    $page = livePage($this, ['name' => 'Services', 'slug' => 'services']);
    $page->url->update(['is_enabled' => false]);
    $page->syncContent(['heading' => 'Old heading', 'body' => ['json' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Kept']]]]], 'html' => '<p>Kept</p>']]);

    $result = mcpCall($this, $this->token, 'update_page', ['id' => $page->id, 'name' => 'Our services', 'content' => ['heading' => 'New heading']]);

    $page->refresh();

    expect($result['isError'])->toBeFalse()
        ->and($page->name)->toBe('Our services')
        ->and($page->slug)->toBe('services')
        ->and($page->url->is_enabled)->toBeFalse()
        ->and($page->contentValues()['heading'])->toBe('New heading')
        ->and($page->contentValues()['body']['html'])->toBe('<p>Kept</p>')
        ->and(McpAuditLog::sole()->changes)->toEqual([
            'name' => ['before' => 'Services', 'after' => 'Our services'],
            'content.heading' => ['before' => 'Old heading', 'after' => 'New heading'],
        ]);
});

test('a live page can\'t be edited without the publish ability', function () {
    $page = livePage($this);

    $result = mcpCall($this, $this->token, 'update_page', ['id' => $page->id, 'content' => ['heading' => 'Changed']]);

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('mcp:cms:publish')
        ->and($page->contentValues()['heading'])->toBe('Live heading')
        ->and(McpAuditLog::sole()->outcome)->toBe(McpOutcome::Denied);

    $publisher = mcpToken($this->user, [ApiTokenAbility::McpCmsContent, ApiTokenAbility::McpCmsPublish]);

    expect(mcpCall($this, $publisher, 'update_page', ['id' => $page->id, 'content' => ['heading' => 'Changed']])['isError'])->toBeFalse()
        ->and($page->fresh()->contentValues()['heading'])->toBe('Changed');
});

test('a draft can\'t be moved or renamed while live pages sit under it', function () {
    $parent = livePage($this, ['slug' => 'work']);
    $parent->url->update(['is_enabled' => false]);
    livePage($this, ['slug' => 'case-study', 'parent_id' => $parent->id]);

    $result = mcpCall($this, $this->token, 'update_page', ['id' => $parent->id, 'slug' => 'projects']);

    expect($result['isError'])->toBeTrue()
        ->and($result['content'][0]['text'])->toContain('live pages under it')
        ->and($parent->fresh()->slug)->toBe('work');

    // Other changes are fine
    expect(mcpCall($this, $this->token, 'update_page', ['id' => $parent->id, 'name' => 'Our work'])['isError'])->toBeFalse();
});

test('publishing and unpublishing need the publish ability', function () {
    $page = livePage($this);
    $page->url->update(['is_enabled' => false]);

    expect(mcpCall($this, $this->token, 'publish_page', ['id' => $page->id]))->toHaveKey('error');

    $publisher = mcpToken($this->user, [ApiTokenAbility::McpCmsPublish]);

    expect(mcpCall($this, $publisher, 'publish_page', ['id' => $page->id])['structuredContent']['page']['status'])->toBe('published')
        ->and($page->url->fresh()->is_enabled)->toBeTrue();

    expect(mcpCall($this, $publisher, 'unpublish_page', ['id' => $page->id])['structuredContent']['page']['status'])->toBe('draft');
});

test('pages can be read by id or URL, with their content', function () {
    $page = livePage($this, ['slug' => 'about']);

    $byUrl = mcpCall($this, $this->token, 'get_page', ['url' => '/about'])['structuredContent']['page'];

    expect($byUrl)
        ->id->toBe($page->id)
        ->template->toBe('service')
        ->status->toBe('published')
        ->and($byUrl['content'])->toBe(['heading' => 'Live heading', 'body' => null]);

    expect(mcpCall($this, $this->token, 'list_pages', ['search' => 'abo'])['structuredContent']['pages'])->toHaveCount(1);
});

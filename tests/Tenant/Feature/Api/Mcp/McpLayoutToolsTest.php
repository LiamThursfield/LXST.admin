<?php

use App\Enums\Api\ApiTokenAbility;
use App\Enums\Api\McpOutcome;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\McpAuditLog;

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->user = mcpUser($this);
    $this->token = mcpToken($this->user, [ApiTokenAbility::McpCmsRead, ApiTokenAbility::McpCmsStructure]);

    $this->template = Template::factory()->ofType(TemplateType::Layout)->create(['name' => 'Site', 'slug' => 'site']);
    TemplateField::factory()->for($this->template)->ofType(TemplateFieldType::Textarea)->create(['name' => 'Footer blurb', 'slug' => 'footer_blurb', 'order' => 0]);
    TemplateField::factory()->for($this->template)->ofType(TemplateFieldType::Wysiwyg)->create(['slug' => 'notice', 'order' => 1]);
});

test('a layout is created from a layout template, with its content', function () {
    $result = mcpCall($this, $this->token, 'create_layout', [
        'name' => 'Site',
        'template_id' => $this->template->id,
        'content' => ['footer_blurb' => 'A small studio in Manchester.', 'notice' => '<p>Booking <em>now</em></p>'],
    ]);

    $layout = Layout::sole();

    expect($result['isError'])->toBeFalse()
        ->and($result['structuredContent']['layout'])->slug->toBe('site')->template->toBe('site')->pages_count->toBe(0)
        ->and($layout->contentValues()['footer_blurb'])->toBe('A small studio in Manchester.')
        ->and($layout->contentValues()['notice']['html'])->toBe('<p>Booking <em>now</em></p>')
        ->and(McpAuditLog::sole())->tool->toBe('create_layout')->outcome->toBe(McpOutcome::Success)->target_id->toBe($layout->id);
});

test('a layout needs a layout template, as in the admin', function () {
    $pageTemplate = Template::factory()->create();

    $result = mcpCall($this, $this->token, 'create_layout', ['name' => 'Site', 'template_id' => $pageTemplate->id]);

    expect($result['isError'])->toBeTrue()
        ->and(Layout::count())->toBe(0)
        ->and(McpAuditLog::sole()->outcome)->toBe(McpOutcome::Invalid);
});

test('updating a layout only changes what\'s sent, merging content field by field', function () {
    mcpCall($this, $this->token, 'create_layout', ['name' => 'Site', 'template_id' => $this->template->id, 'content' => ['footer_blurb' => 'Old blurb', 'notice' => '<p>Kept</p>']]);
    $layout = Layout::sole();

    $result = mcpCall($this, $this->token, 'update_layout', ['id' => $layout->id, 'content' => ['footer_blurb' => 'New blurb']]);

    $layout->refresh();

    expect($result['isError'])->toBeFalse()
        ->and($layout)->name->toBe('Site')->slug->toBe('site')
        ->and($layout->contentValues()['footer_blurb'])->toBe('New blurb')
        ->and($layout->contentValues()['notice']['html'])->toBe('<p>Kept</p>')
        ->and(McpAuditLog::query()->latest('id')->first()->changes)->toEqual([
            'content.footer_blurb' => ['before' => 'Old blurb', 'after' => 'New blurb'],
        ]);
});

test('a layout can be read by slug, and pages can then use it', function () {
    $layout = Layout::factory()->create(['template_id' => $this->template->id, 'slug' => 'site']);
    Page::factory()->create(['layout_id' => $layout->id]);

    $read = mcpCall($this, $this->token, 'get_layout', ['slug' => 'site'])['structuredContent']['layout'];

    expect($read)->id->toBe($layout->id)->pages_count->toBe(1)
        ->and($read['content'])->toBe(['footer_blurb' => null, 'notice' => null]);
});

test('changing layouts needs the structure ability', function () {
    $contentToken = mcpToken($this->user, [ApiTokenAbility::McpCmsRead, ApiTokenAbility::McpCmsContent]);

    expect(mcpToolNames($this, $contentToken))->toContain('get_layout')->not->toContain('create_layout')->not->toContain('update_layout')
        ->and(mcpCall($this, $contentToken, 'create_layout', ['name' => 'Site', 'template_id' => $this->template->id]))->toHaveKey('error')
        ->and(Layout::count())->toBe(0);
});

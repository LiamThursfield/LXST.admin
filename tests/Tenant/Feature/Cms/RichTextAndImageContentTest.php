<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Layout;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Models\FileManager\File;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\RichText;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));

    $this->template = Template::factory()->ofType(TemplateType::Layout)->create();
    TemplateField::factory()->for($this->template)->create(['type' => TemplateFieldType::Wysiwyg, 'name' => 'Body', 'slug' => 'body', 'is_required' => true, 'order' => 0]);
    TemplateField::factory()->for($this->template)->create(['type' => TemplateFieldType::Image, 'name' => 'Hero', 'slug' => 'hero', 'order' => 1]);
});

/**
 * A TipTap document with the given top-level nodes
 *
 * @param  list<array<string, mixed>>  $content
 * @return array<string, mixed>
 */
function doc(array $content): array
{
    return ['type' => 'doc', 'content' => $content];
}

/**
 * @param  list<array<string, mixed>>  $marks
 * @return array<string, mixed>
 */
function paragraph(string $text, array $marks = []): array
{
    return ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text, ...($marks ? ['marks' => $marks] : [])]]];
}

/**
 * @param  array<string, mixed>  $content
 */
function saveLayout(Template $template, array $content): TestResponse
{
    return test()->post(route('admin.cms.layouts.store'), [
        'template_id' => $template->id,
        'name' => 'Main',
        'content' => $content,
    ]);
}

describe('rich text', function () {
    test('the editor\'s JSON is stored with HTML built from it on the server', function () {
        $json = doc([
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Welcome']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'Read the '],
                ['type' => 'text', 'text' => 'docs', 'marks' => [['type' => 'bold'], ['type' => 'link', 'attrs' => ['href' => 'https://example.com']]]],
            ]],
        ]);

        // The HTML sent by the browser is ignored
        saveLayout($this->template, ['body' => ['json' => $json, 'html' => '<script>alert(1)</script>']])
            ->assertSessionHasNoErrors();

        $body = Layout::query()->sole()->contentValues()['body'];

        // Equal rather than identical, as MySQL reorders JSON object keys. The space at the end of
        // "Read the " is kept, as trimming would join it to the next word.
        expect($body['json'])->toEqual($json)
            ->and($body['html'])->toContain('<h2>Welcome</h2>')
            ->and($body['html'])->toContain('Read the <')
            ->and($body['html'])->toContain('href="https://example.com"')
            ->and($body['html'])->toContain('<strong>')
            ->and($body['html'])->not->toContain('script');
    });

    test('unsafe links and images, and content the editor can\'t produce, are refused', function () {
        saveLayout($this->template, ['body' => ['json' => doc([
            paragraph('click', [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]),
            ['type' => 'image', 'attrs' => ['src' => 'data:image/png;base64,AAAA']],
            ['type' => 'codeBlock', 'content' => [['type' => 'text', 'text' => 'x']]],
        ])]])->assertSessionHasErrors('content.body');

        $errors = session('errors')->get('content.body');

        expect($errors)->toContain(__('cms.content.rich_text.unsafe_link'))
            ->and($errors)->toContain(__('cms.content.rich_text.unsafe_image'))
            ->and($errors)->toContain(__('cms.content.rich_text.unsupported', ['type' => 'codeBlock']));
    });

    test('a required rich text field can\'t be left blank', function () {
        saveLayout($this->template, ['body' => ['json' => doc([['type' => 'paragraph']])]])
            ->assertSessionHasErrors(['content.body' => __('validation.required', ['attribute' => 'Body'])]);

        saveLayout($this->template, [])
            ->assertSessionHasErrors(['content.body' => __('validation.required', ['attribute' => 'Body'])]);
    });

    test('links to paths on the site and images from the File Manager are allowed', function () {
        $service = app(RichText::class);

        expect($service->problems(doc([
            paragraph('about', [['type' => 'link', 'attrs' => ['href' => '/about']]]),
            paragraph('mail', [['type' => 'link', 'attrs' => ['href' => 'mailto:hi@example.com']]]),
            ['type' => 'image', 'attrs' => ['src' => 'http://tenant.test/tenancy/assets/file-manager/logo.png', 'alt' => 'Logo']],
        ])))->toBe([])
            ->and($service->problems(doc([paragraph('x', [['type' => 'link', 'attrs' => ['href' => '//evil.test']]])])))
            ->toBe([__('cms.content.rich_text.unsafe_link')]);
    });
});

describe('images', function () {
    beforeEach(function () {
        $this->body = ['json' => doc([paragraph('Hello')])];
    });

    test('an image is stored as its File Manager file and alt text', function () {
        $file = File::factory()->create(['mime_type' => 'image/png']);

        saveLayout($this->template, ['body' => $this->body, 'hero' => ['file_id' => (string) $file->id, 'alt' => 'Our team']])
            ->assertSessionHasNoErrors();

        expect(Layout::query()->sole()->contentValues()['hero'])->toEqual(['file_id' => $file->id, 'alt' => 'Our team']);
    });

    test('an image must be an image from the File Manager', function () {
        $pdf = File::factory()->create(['mime_type' => 'application/pdf']);

        saveLayout($this->template, ['body' => $this->body, 'hero' => ['file_id' => $pdf->id]])
            ->assertSessionHasErrors(['content.hero.file_id' => __('validation.exists', ['attribute' => 'Hero'])]);
    });

    test('an optional image can be left empty', function () {
        saveLayout($this->template, ['body' => $this->body, 'hero' => null])->assertSessionHasNoErrors();

        expect(Layout::query()->sole()->contentValues()['hero'])->toBeNull();
    });
});

test('rich text and image fields can now be added to templates', function () {
    expect(TemplateFieldType::Wysiwyg->isAvailable())->toBeTrue()
        ->and(TemplateFieldType::Image->isAvailable())->toBeTrue();
});

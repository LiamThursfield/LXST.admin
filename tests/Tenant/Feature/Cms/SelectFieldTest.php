<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\Api\ContentPresenter;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function selectFieldData(array $overrides = []): array
{
    return [
        'type' => 'select',
        'name' => 'Style',
        'slug' => 'style',
        'settings' => [
            'options' => [['label' => 'Primary', 'value' => 'primary'], ['label' => 'Text link', 'value' => 'text-link']],
            'default' => 'primary',
        ],
        ...$overrides,
    ];
}

/**
 * @param  array<string, mixed>  $field
 * @return array<string, mixed>
 */
function templateWithField(array $field, TemplateType $type = TemplateType::Page): array
{
    return ['name' => 'With select', 'slug' => 'with-select', 'type' => $type->value, 'fields' => [$field]];
}

/**
 * @param  array<string, mixed>  $content
 */
function savePageWithContent(Template $template, array $content): mixed
{
    return test()->post(route('admin.cms.pages.store'), [
        'template_id' => $template->id,
        'layout_id' => Layout::factory()->create()->id,
        'name' => 'Styled',
        'slug' => 'styled',
        'is_enabled' => true,
        'content' => $content,
    ]);
}

describe('the template builder', function () {
    test('a select field can be added to every type of template, keeping only its options and default', function (TemplateType $type) {
        $field = selectFieldData();
        $field['settings']['options'][0]['_key'] = 3;
        $field['settings']['max_length'] = 5;

        $this->post(route('admin.cms.templates.store'), templateWithField($field, $type))->assertSessionHasNoErrors();

        $settings = TemplateField::query()->sole()->settings;

        expect($settings->options)->toBe([['label' => 'Primary', 'value' => 'primary'], ['label' => 'Text link', 'value' => 'text-link']])
            ->and($settings->default)->toBe('primary')
            ->and($settings->max_length)->toBeNull();
    })->with(TemplateType::cases());

    test('a select field needs at least one option', function () {
        $this->post(route('admin.cms.templates.store'), templateWithField(selectFieldData(['settings' => ['options' => []]])))
            ->assertSessionHasErrors(['fields.0.settings.options' => __('cms.templates.validation.options_required')]);

        $this->post(route('admin.cms.templates.store'), templateWithField(selectFieldData(['settings' => []])))
            ->assertSessionHasErrors(['fields.0.settings.options' => __('cms.templates.validation.options_required')]);
    });

    test('option values must be unique within a field, but not across fields', function () {
        $this->post(route('admin.cms.templates.store'), templateWithField(selectFieldData(['settings' => [
            'options' => [['label' => 'One', 'value' => 'same'], ['label' => 'Two', 'value' => 'same']],
        ]])))->assertSessionHasErrors(['fields.0.settings.options' => __('cms.templates.validation.duplicate_option_value')]);

        $this->post(route('admin.cms.templates.store'), [
            ...templateWithField(selectFieldData()),
            'fields' => [selectFieldData(), selectFieldData(['slug' => 'other_style'])],
        ])->assertSessionHasNoErrors();
    });

    test('the default must be one of the options', function () {
        $this->post(route('admin.cms.templates.store'), templateWithField(selectFieldData(['settings' => [
            'options' => [['label' => 'One', 'value' => 'one']],
            'default' => 'two',
        ]])))->assertSessionHasErrors(['fields.0.settings.default' => __('cms.templates.validation.default_not_an_option')]);
    });

    test('options need a label and a value that\'s safe to keep', function () {
        $this->post(route('admin.cms.templates.store'), templateWithField(selectFieldData(['settings' => [
            'options' => [['label' => '', 'value' => 'has spaces']],
        ]])))->assertSessionHasErrors(['fields.0.settings.options.0.label', 'fields.0.settings.options.0.value']);
    });
});

describe('content', function () {
    beforeEach(function () {
        $this->template = Template::factory()->create();
        TemplateField::factory()->for($this->template)->select(['primary', 'secondary'], 'primary')->create(['slug' => 'style', 'name' => 'Style']);
    });

    test('only the field\'s options are accepted', function () {
        savePageWithContent($this->template, ['style' => 'purple'])->assertSessionHasErrors('content.style');

        savePageWithContent($this->template, ['style' => 'secondary'])->assertSessionHasNoErrors();

        expect(Page::query()->sole()->contentValues()['style'])->toBe('secondary');
    });

    test('the default is never stored, even if it\'s sent', function () {
        savePageWithContent($this->template, ['style' => 'primary'])->assertSessionHasNoErrors();

        expect(Page::query()->sole()->content()->count())->toBe(0);
    });

    test('a required select with a default doesn\'t need a choice, but one without does', function () {
        TemplateField::query()->where('slug', 'style')->update(['is_required' => true]);

        savePageWithContent($this->template, [])->assertSessionHasNoErrors();

        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->select(['primary'])->create(['slug' => 'style', 'name' => 'Style', 'is_required' => true]);

        $this->post(route('admin.cms.pages.store'), [
            'template_id' => $template->id,
            'layout_id' => Layout::factory()->create()->id,
            'name' => 'Other',
            'slug' => 'other',
            'is_enabled' => true,
            'content' => [],
        ])->assertSessionHasErrors('content.style');
    });

    test('an untouched optional component with a default stays empty, so its required fields aren\'t needed', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Text, 'slug' => 'title', 'is_required' => true]);
        TemplateField::factory()->for($card)->select(['primary', 'secondary'], 'primary')->create(['slug' => 'style']);

        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Component, 'slug' => 'card', 'settings' => ['template_id' => $card->id]]);

        savePageWithContent($template, ['card' => ['title' => '', 'style' => null]])->assertSessionHasNoErrors();

        expect(Page::query()->sole()->contentValues()['card'])->toBeNull();
    });

    test('choosing another option in an optional component means it\'s being filled in', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Text, 'slug' => 'title', 'is_required' => true]);
        TemplateField::factory()->for($card)->select(['primary', 'secondary'], 'primary')->create(['slug' => 'style']);

        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Component, 'slug' => 'card', 'settings' => ['template_id' => $card->id]]);

        savePageWithContent($template, ['card' => ['title' => '', 'style' => 'secondary']])
            ->assertSessionHasErrors('content.card.title');
    });
});

describe('the API', function () {
    beforeEach(function () {
        $this->template = Template::factory()->create();
        $this->field = TemplateField::factory()->for($this->template)->select(['primary', 'secondary'], 'primary')->create(['slug' => 'style']);
        $this->present = fn (array $values) => app(ContentPresenter::class)->present($this->template->fresh('fields'), $values)['style'];
    });

    test('it returns the chosen value', function () {
        expect(($this->present)(['style' => 'secondary']))->toBe('secondary');
    });

    test('it falls back to the default when blank or the option has been removed', function () {
        expect(($this->present)([]))->toBe('primary')
            ->and(($this->present)(['style' => 'gone']))->toBe('primary');
    });

    test('changing the default updates content that didn\'t choose', function () {
        $this->field->settings->default = 'secondary';
        $this->field->save();

        expect(($this->present)([]))->toBe('secondary');
    });

    test('with no default it comes back as null', function () {
        $this->field->settings->default = null;
        $this->field->save();

        expect(($this->present)([]))->toBeNull()
            ->and(($this->present)(['style' => 'gone']))->toBeNull();
    });
});

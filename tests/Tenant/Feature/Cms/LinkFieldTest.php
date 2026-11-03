<?php

use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateType;
use App\Models\Cms\Layout;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\TemplateField;
use App\Services\Authorisation\Enums\CmsPermission;
use App\Services\Cms\Api\ContentPresenter;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs($this->createUserWithPermissions(CmsPermission::cases()));
    $this->template = Template::factory()->create();
    TemplateField::factory()->for($this->template)->create(['type' => TemplateFieldType::Link, 'name' => 'Destination', 'slug' => 'destination']);
});

/**
 * @param  array<string, mixed>  $content
 */
function savePageWithLink(Template $template, array $content): mixed
{
    return test()->post(route('admin.cms.pages.store'), [
        'template_id' => $template->id,
        'layout_id' => Layout::factory()->create()->id,
        'name' => 'Linked',
        'slug' => 'linked',
        'is_enabled' => true,
        'content' => $content,
    ]);
}

/**
 * The page just saved by savePageWithLink()
 */
function linkedPage(): Page
{
    return Page::query()->where('slug', 'linked')->sole();
}

describe('saving', function () {
    test('a link field can be added to every type of template', function (TemplateType $type) {
        $this->post(route('admin.cms.templates.store'), [
            'name' => 'With link',
            'slug' => 'with-link',
            'type' => $type->value,
            'fields' => [['type' => 'link', 'name' => 'Destination', 'slug' => 'destination']],
        ])->assertSessionHasNoErrors();
    })->with(TemplateType::cases());

    test('a page link stores the page, and nothing else that was sent', function () {
        $target = Page::factory()->create();

        savePageWithLink($this->template, ['destination' => ['link_type' => 'page', 'page_id' => (string) $target->id, 'url' => '/ignored', 'target' => '_blank', 'extra' => 'dropped']])
            ->assertSessionHasNoErrors();

        expect(linkedPage()->contentValues()['destination'])->toEqual(['link_type' => 'page', 'page_id' => $target->id, 'url' => null, 'target' => '_blank']);
    });

    test('a URL link stores the URL, trimmed', function () {
        savePageWithLink($this->template, ['destination' => ['link_type' => 'url', 'page_id' => 5, 'url' => ' https://example.com ', 'target' => null]])
            ->assertSessionHasNoErrors();

        expect(linkedPage()->contentValues()['destination'])->toEqual(['link_type' => 'url', 'page_id' => null, 'url' => 'https://example.com', 'target' => null]);
    });

    test('unsafe URLs are refused', function (string $url) {
        savePageWithLink($this->template, ['destination' => ['link_type' => 'url', 'url' => $url]])
            ->assertSessionHasErrors(['content.destination.url' => __('cms.menus.validation.url')]);
    })->with(['javascript:alert(1)', 'data:text/html,hi', '//example.com', 'example.com']);

    test('safe URLs are accepted', function (string $url) {
        savePageWithLink($this->template, ['destination' => ['link_type' => 'url', 'url' => $url]])
            ->assertSessionHasNoErrors();
    })->with(['https://example.com', 'http://example.com', 'mailto:hi@example.com', 'tel:+441234567', '/contact', '#top']);

    test('a page link must be to a page that exists, and the type and target must be known', function () {
        savePageWithLink($this->template, ['destination' => ['link_type' => 'page', 'page_id' => 999999]])
            ->assertSessionHasErrors('content.destination.page_id');

        savePageWithLink($this->template, ['destination' => ['link_type' => 'other', 'url' => '/contact']])
            ->assertSessionHasErrors('content.destination.link_type');

        savePageWithLink($this->template, ['destination' => ['link_type' => 'url', 'url' => '/contact', 'target' => '_parent']])
            ->assertSessionHasErrors('content.destination.target');
    });

    test('a required link needs somewhere to go', function () {
        TemplateField::query()->where('slug', 'destination')->update(['is_required' => true]);

        savePageWithLink($this->template, [])
            ->assertSessionHasErrors(['content.destination' => __('validation.required', ['attribute' => 'Destination'])]);

        savePageWithLink($this->template, ['destination' => ['link_type' => 'page', 'page_id' => null]])
            ->assertSessionHasErrors(['content.destination.page_id' => __('validation.required', ['attribute' => 'Destination'])]);
    });

    test('a link that goes nowhere stores nothing, so an untouched optional component stays empty', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Text, 'slug' => 'label', 'is_required' => true]);
        TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Link, 'slug' => 'destination']);

        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Component, 'slug' => 'card', 'settings' => ['template_id' => $card->id]]);

        savePageWithLink($template, ['card' => ['label' => '', 'destination' => null]])->assertSessionHasNoErrors();

        expect(linkedPage()->contentValues()['card'])->toBeNull();
    });

    test('filling in a link in an optional component means it\'s being filled in', function () {
        $card = Template::factory()->ofType(TemplateType::Component)->create();
        TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Text, 'slug' => 'label', 'is_required' => true]);
        TemplateField::factory()->for($card)->create(['type' => TemplateFieldType::Link, 'slug' => 'destination']);

        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Component, 'slug' => 'card', 'settings' => ['template_id' => $card->id]]);

        savePageWithLink($template, ['card' => ['label' => '', 'destination' => ['link_type' => 'url', 'url' => '/contact']]])
            ->assertSessionHasErrors('content.card.label');
    });

    test('whether a link is needed is decided for each row', function () {
        $row = Template::factory()->ofType(TemplateType::Repeater)->create();
        TemplateField::factory()->for($row)->create(['type' => TemplateFieldType::Link, 'slug' => 'destination']);

        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Repeater, 'slug' => 'rows', 'settings' => ['template_id' => $row->id]]);

        savePageWithLink($template, ['rows' => [
            ['destination' => ['link_type' => 'url', 'url' => 'javascript:alert(1)']],
            ['destination' => null],
        ]])->assertSessionHasErrors('content.rows.0.destination.url')
            ->assertSessionDoesntHaveErrors('content.rows.1.destination.url');
    });

    test('the editors get the pages to choose from', function () {
        $page = Page::factory()->create();

        foreach (['admin.cms.pages.create', 'admin.cms.layouts.create', 'admin.cms.saved-components.create'] as $route) {
            $this->get(route($route))
                ->assertInertia(fn (Assert $inertia) => $inertia->where('pageOptions.0.value', $page->id));
        }
    });
});

describe('the API', function () {
    beforeEach(function () {
        $this->present = fn (array $link) => app(ContentPresenter::class)->present($this->template->load('fields'), ['destination' => $link])['destination'];
    });

    test('a URL link comes back as its URL and target', function () {
        expect(($this->present)(['link_type' => 'url', 'page_id' => null, 'url' => 'https://example.com', 'target' => '_blank']))
            ->toBe(['url' => 'https://example.com', 'target' => '_blank']);
    });

    test('a page link follows the page\'s current URL', function () {
        $target = Page::factory()->create(['slug' => 'old']);
        $link = ['link_type' => 'page', 'page_id' => $target->id, 'url' => null, 'target' => null];

        expect(($this->present)($link)['url'])->toBe($target->url->url_full);

        $target->url->update(['url_full' => '/moved']);

        expect(($this->present)($link))->toBe(['url' => '/moved', 'target' => null]);
    });

    test('links to unpublished or deleted pages come back as null', function () {
        $draft = Page::factory()->create();
        $draft->url->update(['is_enabled' => false]);

        expect(($this->present)(['link_type' => 'page', 'page_id' => $draft->id, 'url' => null, 'target' => null]))->toBeNull()
            ->and(($this->present)(['link_type' => 'page', 'page_id' => 999999, 'url' => null, 'target' => null]))->toBeNull();
    });

    test('many links don\'t mean a query for each', function () {
        $row = Template::factory()->ofType(TemplateType::Repeater)->create();
        TemplateField::factory()->for($row)->create(['type' => TemplateFieldType::Link, 'slug' => 'destination']);
        $template = Template::factory()->create();
        TemplateField::factory()->for($template)->create(['type' => TemplateFieldType::Repeater, 'slug' => 'rows', 'settings' => ['template_id' => $row->id]]);
        $template->load('fields');

        $link = ['destination' => ['link_type' => 'page', 'page_id' => Page::factory()->create()->id, 'url' => null, 'target' => null]];

        $countQueries = function (int $rows) use ($template, $link) {
            $presenter = app(ContentPresenter::class);
            DB::connection('tenant')->enableQueryLog();
            DB::connection('tenant')->flushQueryLog();
            $presenter->present($template, ['rows' => array_fill(0, $rows, $link)]);

            return count(DB::connection('tenant')->getQueryLog());
        };

        expect($countQueries(12))->toBe($countQueries(2));
    });
});

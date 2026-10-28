<?php

namespace Database\Seeders;

use App\Data\Cms\MenuItemData;
use App\Data\Cms\TemplateFieldSettings;
use App\Enums\Cms\TemplateFieldType;
use App\Enums\Cms\TemplateFieldWidth;
use App\Enums\Cms\TemplateType;
use App\Enums\Crm\OrganisationUnitType;
use App\Enums\TenantModule;
use App\Models\Cms\Layout;
use App\Models\Cms\Menu;
use App\Models\Cms\Page;
use App\Models\Cms\PageView;
use App\Models\Cms\Redirect;
use App\Models\Cms\Template;
use App\Models\Crm\Form;
use App\Models\Crm\OrganisationUnit;
use App\Models\FileManager\File;
use App\Services\Cms\RichText;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A small, realistic website to test the CMS admin and the headless API with (#166): templates
 * using every field type (a page and a component laid out in sections, with half-width fields),
 * two layouts, nested menus, pages in every publish status, redirects, and a few weeks of page views.
 *
 * Run within a tenant, e.g. `php artisan tenants:seed --class=CmsSeeder`. Safe to re-run: each kind
 * of data is only seeded when none exists yet.
 */
class CmsSeeder extends Seeder
{
    /** How many days of page views to make, up to today */
    protected const int VIEW_DAYS = 28;

    public function __construct(protected RichText $richText) {}

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $templates = $this->seedTemplates();
        $menus = $this->seedMenus();
        $layouts = $this->seedLayouts($templates['layout'], $menus);
        $pages = $this->seedPages($templates, $layouts);
        $this->fillMenus($menus, $pages);
        $this->seedRedirects();
        $this->seedPageViews($pages);
    }

    /**
     * A layout template, a component, a repeater, and two page templates using every field type
     *
     * @return array{layout: Template, cta: Template, feature: Template, standard: Template, landing: Template}
     */
    protected function seedTemplates(): array
    {
        $template = fn (string $slug, TemplateType $type, string $name, string $description, array $fields) => $this->template($slug, $type, $name, $description, $fields);

        $cta = $template('call-to-action', TemplateType::Component, 'Call to action', 'A heading, a short message and a button.', [
            ['heading', TemplateFieldType::Text, 'Heading', true],
            ['message', TemplateFieldType::Textarea, 'Message', false, ['rows' => 3]],
            ['button_label', TemplateFieldType::Text, 'Button label', false, ['max_length' => 40]],
            ['button_url', TemplateFieldType::Text, 'Button link', false],
        ]);
        $this->sections($cta, [
            ['Message', null, false, ['heading', 'message']],
            ['Button', 'Where the button takes visitors.', false, ['button_label', 'button_url']],
        ], halfWidth: ['button_label', 'button_url']);

        $feature = $template('feature', TemplateType::Repeater, 'Feature', 'One item in a list of features.', [
            ['title', TemplateFieldType::Text, 'Title', true],
            ['description', TemplateFieldType::Textarea, 'Description', false, ['rows' => 2]],
            ['image', TemplateFieldType::Image, 'Image', false],
        ]);

        $layout = $template('site-layout', TemplateType::Layout, 'Site layout', 'The header and footer around every page.', [
            ['site_title', TemplateFieldType::Text, 'Site title', true],
            ['main_menu', TemplateFieldType::CmsMenu, 'Main menu', false],
            ['footer_menu', TemplateFieldType::CmsMenu, 'Footer menu', false],
            ['footer_text', TemplateFieldType::Textarea, 'Footer text', false, ['rows' => 2]],
        ]);

        $crm = tenant()?->hasModule(TenantModule::Crm) ?? false;

        $standard = $template('standard-page', TemplateType::Page, 'Standard page', 'A hero, rich text, features and a call to action.', [
            ['hero_title', TemplateFieldType::Text, 'Hero title', true, ['max_length' => 80]],
            ['hero_image', TemplateFieldType::Image, 'Hero image', false],
            ['body', TemplateFieldType::Wysiwyg, 'Body', false],
            ['features', TemplateFieldType::Repeater, 'Features', false, ['template_id' => $feature->id, 'max_items' => 6]],
            ['call_to_action', TemplateFieldType::Component, 'Call to action', false, ['template_id' => $cta->id]],
            ...($crm ? [['contact_form', TemplateFieldType::CrmForm, 'Contact form', false]] : []),
        ]);
        $this->sections($standard, [
            ['Hero', 'The banner at the top of the page.', false, ['hero_title', 'hero_image']],
            ['Body', null, false, ['body']],
            ['Features', 'What we offer, as a list.', false, ['features']],
            ['Call to action', 'Where the page asks visitors to get in touch.', true, ['call_to_action', 'contact_form']],
        ], halfWidth: ['hero_title', 'hero_image']);

        $landing = $template('landing-page', TemplateType::Page, 'Landing page', 'A short page with a headline number, e.g. for a campaign.', [
            ['headline', TemplateFieldType::Text, 'Headline', true],
            ['intro', TemplateFieldType::Textarea, 'Intro', false, ['rows' => 4]],
            ['years_running', TemplateFieldType::Number, 'Years running', false, ['min' => 0, 'max' => 100]],
            ...($crm ? [['office', TemplateFieldType::CrmOrganisationUnit, 'Office', false, ['organisation_unit_type' => OrganisationUnitType::Location->value]]] : []),
        ]);

        return compact('layout', 'cta', 'feature', 'standard', 'landing');
    }

    /**
     * @return array{main: Menu, footer: Menu}
     */
    protected function seedMenus(): array
    {
        return [
            'main' => Menu::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main menu']),
            'footer' => Menu::query()->firstOrCreate(['slug' => 'footer'], ['name' => 'Footer menu']),
        ];
    }

    /**
     * @param  array{main: Menu, footer: Menu}  $menus
     * @return array{default: Layout, minimal: Layout}
     */
    protected function seedLayouts(Template $template, array $menus): array
    {
        $layout = function (string $slug, string $name, array $values) use ($template): Layout {
            $layout = Layout::query()->firstOrCreate(['slug' => $slug], ['name' => $name, 'template_id' => $template->id]);

            if ($layout->content()->doesntExist()) {
                $layout->syncContent($values);
            }

            return $layout;
        };

        return [
            'default' => $layout('default', 'Default', [
                'site_title' => 'Northside Sound',
                'main_menu' => $menus['main']->id,
                'footer_menu' => $menus['footer']->id,
                'footer_text' => 'Weddings, parties and club nights across the north. © Northside Sound.',
            ]),
            'minimal' => $layout('minimal', 'Minimal', [
                'site_title' => 'Northside Sound',
                'main_menu' => $menus['main']->id,
            ]),
        ];
    }

    /**
     * Pages in every publish status, nested two deep, with content for each template field
     *
     * @param  array{layout: Template, cta: Template, feature: Template, standard: Template, landing: Template}  $templates
     * @param  array{default: Layout, minimal: Layout}  $layouts
     * @return array<string, Page>
     */
    protected function seedPages(array $templates, array $layouts): array
    {
        if (Page::query()->exists()) {
            return Page::query()->with('url')->get()->keyBy(fn (Page $page) => $page->slug === '' ? 'home' : $page->slug)->all();
        }

        $now = CarbonImmutable::now();
        $image = File::query()->where('mime_type', 'like', 'image/%')->value('id');
        $form = Form::query()->value('id');
        $office = OrganisationUnit::query()->where('type', OrganisationUnitType::Location)->value('id');

        $page = function (string $key, string $slug, string $name, Template $template, Layout $layout, array $values, array $url = [], ?Page $parent = null) use ($image): Page {
            $page = Page::query()->create([
                'template_id' => $template->id,
                'layout_id' => $layout->id,
                'parent_id' => $parent?->id,
                'name' => $name,
                'slug' => $slug,
            ]);

            $page->url()->create([
                'url_main' => $page->urlMain(),
                'url_full' => $page->urlFull($parent?->url?->url_full),
                'is_enabled' => true,
                ...$url,
            ]);
            $page->metadata()->create([
                'title' => "{$name} | Northside Sound",
                'description' => Str::limit((string) ($values['hero_title'] ?? $values['headline'] ?? $name), 150),
                'og_title' => $name,
                'og_type' => 'website',
                'og_image_file_id' => $image,
            ]);
            $page->syncContent($values);

            return $page->load('url');
        };

        $standard = fn (string $hero, array $paragraphs, array $extra = []) => [
            'hero_title' => $hero,
            'hero_image' => $image === null ? null : ['file_id' => $image, 'alt' => $hero],
            'body' => $this->richText($paragraphs),
            ...$extra,
        ];
        // Each feature's image, from the File Manager seeder's gig photos (null without them)
        $featureImage = function (string $path, string $alt): ?array {
            $id = File::query()->where('path', $path)->value('id');

            return $id === null ? null : ['file_id' => $id, 'alt' => $alt];
        };
        $features = fn () => [
            ['title' => 'Weddings', 'description' => 'From the first dance to the last song, planned with you.', 'image' => $featureImage('images/gigs/wedding-barn.jpg', 'A barn wedding reception')],
            ['title' => 'Parties', 'description' => 'Birthdays, anniversaries and everything in between.', 'image' => $featureImage('images/gigs/rooftop-party.jpg', 'A rooftop party at sunset')],
            ['title' => 'Club nights', 'description' => 'House, disco and edits, mixed live all night.', 'image' => $featureImage('images/gigs/club-night.jpg', 'The DJ booth at a club night')],
        ];
        $cta = ['heading' => 'Planning an event?', 'message' => 'Tell us the date and we\'ll let you know if we\'re free.', 'button_label' => 'Get in touch', 'button_url' => '/contact'];

        $home = $page('home', '', 'Home', $templates['standard'], $layouts['default'], $standard(
            'Music for every moment',
            ['DJs for weddings, parties and club nights across the north.', 'We read the room, take requests and keep the floor full.'],
            ['features' => $features(), 'call_to_action' => $cta],
        ));
        $about = $page('about', 'about', 'About', $templates['standard'], $layouts['default'], $standard(
            'About us',
            ['Northside Sound started with two friends and a pair of decks in 2014.', 'Ten years on, we play over a hundred events a year.'],
        ));
        $team = $page('team', 'team', 'Our team', $templates['standard'], $layouts['default'], $standard('Meet the team', ['Our DJs each bring their own sound, from soul to techno.']), [], $about);
        $history = $page('history', 'history', 'Our history', $templates['standard'], $layouts['default'], $standard('How we started', ['A decade of weddings, festivals and late nights.']), [], $about);
        $services = $page('services', 'services', 'Services', $templates['standard'], $layouts['default'], $standard(
            'What we do',
            ['Every booking includes a planning call, a custom playlist and a song request page for your guests.'],
            ['features' => $features(), 'call_to_action' => $cta],
        ));
        $contact = $page('contact', 'contact', 'Contact', $templates['standard'], $layouts['minimal'], $standard(
            'Get in touch',
            ['Send us a message and we\'ll reply within a day.'],
            ['contact_form' => $form],
        ));
        $privacy = $page('privacy', 'privacy', 'Privacy policy', $templates['standard'], $layouts['minimal'], $standard('Privacy policy', ['We only keep your details to plan your event.']));
        $terms = $page('terms', 'terms', 'Terms', $templates['standard'], $layouts['minimal'], $standard('Terms and conditions', ['A 25% deposit secures your date.']));

        $summer = $page('summer-parties', 'summer-parties', 'Summer parties', $templates['landing'], $layouts['default'], [
            'headline' => 'Summer garden parties',
            'intro' => 'Book a summer date before the end of the month and get a free uplighting package.',
            'years_running' => 10,
            'office' => $office,
        ], ['published_at' => $now->addWeek()]);
        $draft = $page('coming-soon', 'coming-soon', 'Coming soon', $templates['standard'], $layouts['default'], $standard('Corporate events', ['Something new is on the way.']), ['is_enabled' => false]);
        $offer = $page('winter-offer', 'winter-offer', 'Winter offer', $templates['landing'], $layouts['default'], [
            'headline' => 'Winter weddings, 10% off',
            'intro' => 'The offer has ended, but get in touch for this year\'s prices.',
            'years_running' => 10,
        ], ['published_at' => $now->subMonths(3), 'expired_at' => $now->subWeek()]);

        return compact('home', 'about', 'team', 'history', 'services', 'contact', 'privacy', 'terms', 'summer', 'draft', 'offer');
    }

    /**
     * Fills in the menus once the pages they link to exist: page links nested two deep, and
     * outside links opening in a new tab
     *
     * @param  array{main: Menu, footer: Menu}  $menus
     * @param  array<string, Page>  $pages
     */
    protected function fillMenus(array $menus, array $pages): void
    {
        $link = fn (string $label, ?Page $page, array $children = []) => new MenuItemData(
            label: $label,
            link_type: MenuItemData::LINK_PAGE,
            page_id: $page?->id,
            children: $children,
        );
        $external = fn (string $label, string $url) => new MenuItemData(label: $label, url: $url, target: '_blank', rel: 'noopener');

        if ($menus['main']->menu_items === null || $menus['main']->menu_items->count() === 0) {
            $menus['main']->update(['menu_items' => [
                $link('Home', $pages['home'] ?? null),
                $link('About', $pages['about'] ?? null, [
                    $link('Our team', $pages['team'] ?? null),
                    $link('Our history', $pages['history'] ?? null),
                ]),
                $link('Services', $pages['services'] ?? null),
                $link('Contact', $pages['contact'] ?? null),
            ]]);
        }

        if ($menus['footer']->menu_items === null || $menus['footer']->menu_items->count() === 0) {
            $menus['footer']->update(['menu_items' => [
                $link('Privacy policy', $pages['privacy'] ?? null),
                $link('Terms', $pages['terms'] ?? null),
                $external('Instagram', 'https://www.instagram.com/'),
            ]]);
        }
    }

    /**
     * Permanent and temporary redirects, and one of each publish status
     */
    protected function seedRedirects(): void
    {
        if (Redirect::query()->exists()) {
            return;
        }

        $now = CarbonImmutable::now();

        foreach ([
            ['/about-us', '/about', true, true, null, null],
            ['/book', '/contact', false, true, null, null],
            ['/blog', 'https://example.com/blog', false, true, null, null],
            ['/old-prices', '/services', true, false, null, null],
            ['/summer', '/summer-parties', false, true, $now->addWeek(), null],
            ['/winter', '/winter-offer', false, true, $now->subMonths(3), $now->subWeek()],
        ] as [$from, $to, $permanent, $enabled, $publishedAt, $expiredAt]) {
            Redirect::query()->create([
                'url_from' => $from,
                'url_to' => $to,
                'is_permanent' => $permanent,
                'is_enabled' => $enabled,
                'published_at' => $publishedAt,
                'expired_at' => $expiredAt,
            ]);
        }
    }

    /**
     * A few weeks of views of the published pages, from a pool of returning visitors, with some
     * coming from search, social and a newsletter
     *
     * @param  array<string, Page>  $pages
     */
    protected function seedPageViews(array $pages): void
    {
        if (PageView::query()->exists()) {
            return;
        }

        $published = collect($pages)->only(['home', 'about', 'team', 'history', 'services', 'contact', 'privacy', 'terms'])->filter();
        // How popular each page is, relative to the home page
        $weights = ['home' => 10, 'services' => 6, 'contact' => 4, 'about' => 3, 'team' => 2, 'history' => 1, 'privacy' => 1, 'terms' => 1];
        $visitors = collect(range(1, 80))->map(fn () => hash('sha256', fake()->uuid()))->all();
        $sources = [
            [null, null],
            ['https://www.google.com/', null],
            ['https://www.instagram.com/', ['utm_source' => 'instagram', 'utm_medium' => 'social']],
            [null, ['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'spring']],
        ];
        $userAgents = collect(range(1, 6))->map(fn () => fake()->userAgent())->all();
        $today = CarbonImmutable::today();
        $rows = [];

        foreach (range(self::VIEW_DAYS - 1, 0) as $daysAgo) {
            $day = $today->subDays($daysAgo);
            // Busier at weekends, when people plan events
            $busy = $day->isWeekend() ? 2 : 1;

            foreach ($published as $key => $page) {
                foreach (range(1, fake()->numberBetween(1, 4) * ($weights[$key] ?? 1) * $busy) as $view) {
                    [$referrer, $source] = fake()->randomElement($sources);
                    $rows[] = [
                        'viewable_type' => $page->getMorphClass(),
                        'viewable_id' => $page->id,
                        'url' => $page->url?->url_full ?? '/',
                        'referrer' => $referrer,
                        'user_agent' => fake()->randomElement($userAgents),
                        'ip_address' => '203.0.113.0',
                        'visitor_hash' => fake()->randomElement($visitors),
                        'session_id' => null,
                        'source' => $source === null ? null : json_encode($source),
                        'additional_data' => null,
                        'viewed_at' => $day->setTime(fake()->numberBetween(7, 22), fake()->numberBetween(0, 59)),
                    ];
                }
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            PageView::query()->insert($chunk);
        }
    }

    /**
     * A template with its fields, made once by its slug
     *
     * @param  list<array{0: string, 1: TemplateFieldType, 2: string, 3: bool, 4?: array<string, mixed>}>  $fields
     */
    protected function template(string $slug, TemplateType $type, string $name, string $description, array $fields): Template
    {
        $template = Template::query()->firstOrCreate(['slug' => $slug], ['type' => $type, 'name' => $name, 'description' => $description]);

        if ($template->fields()->doesntExist()) {
            foreach ($fields as $order => $field) {
                $template->fields()->create([
                    'slug' => $field[0],
                    'type' => $field[1],
                    'name' => $field[2],
                    'is_required' => $field[3],
                    'order' => $order,
                    'settings' => isset($field[4]) ? TemplateFieldSettings::from($field[4]) : null,
                ]);
            }
        }

        return $template;
    }

    /**
     * Groups a template's fields into sections (name, description, whether it starts collapsed, and
     * its fields' slugs) and makes some fields half width. Only done while the template has no
     * sections, so re-running changes nothing, and a template seeded before sections gets them.
     *
     * @param  list<array{0: string, 1: string|null, 2: bool, 3: list<string>}>  $sections
     * @param  list<string>  $halfWidth
     */
    protected function sections(Template $template, array $sections, array $halfWidth = []): void
    {
        if ($template->sections()->exists()) {
            return;
        }

        foreach ($sections as $order => [$name, $description, $isCollapsed, $slugs]) {
            $section = $template->sections()->create([
                'name' => $name,
                'description' => $description,
                'is_collapsed' => $isCollapsed,
                'order' => $order,
            ]);

            $template->fields()->whereIn('slug', $slugs)->update(['section_id' => $section->id]);
        }

        foreach ($template->fields()->whereIn('slug', $halfWidth)->get() as $field) {
            $field->update(['settings' => TemplateFieldSettings::from([
                ...($field->settings?->toArray() ?? []),
                'width' => TemplateFieldWidth::Half,
            ])]);
        }
    }

    /**
     * A rich text value as the editor saves it: the document and its HTML
     *
     * @param  list<string>  $paragraphs
     * @return array{json: array<string, mixed>, html: string}
     */
    protected function richText(array $paragraphs): array
    {
        $document = [
            'type' => 'doc',
            'content' => array_map(fn (string $text) => ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $text]]], $paragraphs),
        ];

        return ['json' => $document, 'html' => $this->richText->toHtml($document)];
    }
}

<?php

namespace App\Http\Requests\Tenant\Admin\Cms\Concerns;

use App\Enums\Cms\TemplateType;
use App\Models\Cms\Metadata;
use App\Models\Cms\Page;
use App\Models\Cms\Template;
use App\Models\Cms\Url;
use App\Models\FileManager\File;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesPage
{
    use ValidatesContent;

    protected ?Template $resolvedTemplate = null;

    /**
     * The page being edited, if any
     */
    abstract protected function currentPage(): ?Page;

    /**
     * Slugs are lower case, so "About" and "about" are the same page
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::lower(trim($this->input('slug'), " \t\n\r\0\x0B/"))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function pageRules(): array
    {
        $page = $this->currentPage();

        return [
            'template_id' => ['required', 'integer', Rule::exists('cms_templates', 'id')->where('type', TemplateType::Page->value)],
            'layout_id' => ['required', 'integer', Rule::exists('cms_layouts', 'id')],
            'parent_id' => ['nullable', 'integer', Rule::exists('cms_pages', 'id'), $this->parentRule($page)],
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(-[a-z0-9]+)*$/',
                // The blank slug (home page), sibling and URL checks are in after(), as Laravel
                // skips rules like these when the slug is left empty
            ],

            'is_enabled' => ['required', 'boolean'],
            'published_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'expired_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'after:published_at'],

            'metadata' => ['nullable', 'array'],
            'metadata.title' => ['nullable', 'string', 'max:255'],
            'metadata.description' => ['nullable', 'string', 'max:255'],
            'metadata.keywords' => ['nullable', 'string', 'max:255'],
            'metadata.canonical' => ['nullable', 'string', 'max:255'],
            'metadata.og_title' => ['nullable', 'string', 'max:255'],
            'metadata.og_description' => ['nullable', 'string', 'max:255'],
            'metadata.og_type' => ['nullable', 'string', 'max:50'],
            'metadata.og_url' => ['nullable', 'string', 'max:255'],
            'metadata.og_image_file_id' => [
                'nullable',
                'integer',
                Rule::exists(File::class, 'id')->where(fn (Builder $query) => $query->where('mime_type', 'like', 'image/%')),
            ],

            ...$this->contentRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'template_id.exists' => __('cms.pages.validation.template_type'),
            'slug.regex' => __('cms.pages.validation.slug_format'),
            'expired_at.after' => __('cms.pages.validation.expires_before_publish'),
            'metadata.og_image_file_id.exists' => __('cms.pages.validation.og_image'),
        ];
    }

    /**
     * A page can't be its own parent or sit under one of its own descendants, and the home page
     * (blank slug) can't have children, as their URLs would clash with top-level pages'
     */
    protected function parentRule(?Page $page): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($page): void {
            $parent = is_numeric($value) ? Page::query()->find((int) $value) : null;

            if (! $parent instanceof Page) {
                return;
            }

            if ($parent->slug === '') {
                $fail(__('cms.pages.validation.parent_is_home'));

                return;
            }

            if ($page !== null && ($parent->id === $page->id || $page->descendants()->contains('id', $parent->id))) {
                $fail(__('cms.pages.validation.parent_loop'));
            }
        };
    }

    /**
     * Only a top-level page can have a blank slug (the home page), slugs are unique among a page's
     * siblings, and the resulting URL must be free
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['slug', 'parent_id'])) {
                return;
            }

            $page = $this->currentPage();
            $slug = (string) ($this->input('slug') ?? '');
            $parentId = is_numeric($this->input('parent_id')) ? (int) $this->input('parent_id') : null;

            if ($slug === '' && $parentId !== null) {
                $validator->errors()->add('slug', __('cms.pages.validation.slug_required'));

                return;
            }

            $siblingHasSlug = Page::query()
                ->where('parent_id', $parentId)
                ->where('slug', $slug)
                ->when($page, fn ($query, Page $current) => $query->whereKeyNot($current->id))
                ->exists();

            if ($siblingHasSlug) {
                $validator->errors()->add('slug', $slug === '' ? __('cms.pages.validation.home_taken') : __('cms.pages.validation.slug_taken'));

                return;
            }

            $url = $this->fullUrl();
            $taken = $url !== null && Url::query()
                ->where('url_full', $url)
                ->when($page?->url, fn ($query, $own) => $query->whereKeyNot($own->id))
                ->exists();

            if ($taken) {
                $validator->errors()->add('slug', __('cms.pages.validation.url_taken', ['url' => $url]));
            }
        }];
    }

    /**
     * The URL the page would have with the submitted slug and parent
     */
    public function fullUrl(): ?string
    {
        $slug = is_string($this->input('slug')) ? $this->input('slug') : '';
        $parent = is_numeric($this->input('parent_id')) ? Page::query()->with('url')->find((int) $this->input('parent_id')) : null;

        if (is_numeric($this->input('parent_id')) && ! $parent instanceof Page) {
            return null;
        }

        return (new Page(['slug' => $slug]))->urlFull($parent?->url?->url_full);
    }

    protected function contentTemplate(): ?Template
    {
        $id = $this->input('template_id');

        if (! is_numeric($id)) {
            return null;
        }

        return $this->resolvedTemplate ??= Template::query()
            ->where('type', TemplateType::Page)
            ->whereKey((int) $id)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function pageAttributes(): array
    {
        return [
            'template_id' => $this->integer('template_id'),
            'layout_id' => $this->integer('layout_id'),
            'parent_id' => $this->filled('parent_id') ? $this->integer('parent_id') : null,
            'name' => $this->string('name')->toString(),
            'slug' => (string) ($this->validated('slug') ?? ''),
        ];
    }

    /**
     * The publishing window, with dates entered on the user's clock stored as UTC
     *
     * @return array{is_enabled: bool, published_at: mixed, expired_at: mixed}
     */
    public function publishingAttributes(): array
    {
        /** @var User $user */
        $user = $this->user();

        return [
            'is_enabled' => $this->boolean('is_enabled'),
            'published_at' => $this->filled('published_at') ? $user->parseLocal($this->string('published_at')->toString()) : null,
            'expired_at' => $this->filled('expired_at') ? $user->parseLocal($this->string('expired_at')->toString()) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function metadataAttributes(): array
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $this->validated('metadata') ?? [];

        return collect(Metadata::FIELDS)
            ->mapWithKeys(fn (string $field) => [$field => filled($metadata[$field] ?? null) ? $metadata[$field] : null])
            ->all();
    }
}

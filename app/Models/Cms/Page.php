<?php

namespace App\Models\Cms;

use App\Models\Concerns\HasContent;
use App\Models\Concerns\HasMetadata;
use App\Models\Concerns\HasUrl;
use App\Models\Concerns\HasViews;
use Database\Factories\Cms\PageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * A page on the site: its fields come from a page template, it sits in a layout, and its URL is
 * its parent's URL plus its own slug. A top-level page with a blank slug is the home page (/).
 *
 * @property int $id
 * @property int $template_id
 * @property int $layout_id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property-read Layout $layout
 * @property-read Page|null $parent
 * @property-read Collection<int, Page> $children
 * @property-read int|null $children_count
 */
class Page extends Model
{
    use HasContent;

    /** @use HasFactory<PageFactory> */
    use HasFactory;

    use HasMetadata;
    use HasUrl;
    use HasViews;

    protected $table = 'cms_pages';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'template_id',
        'layout_id',
        'parent_id',
        'name',
        'slug',
    ];

    /**
     * @return BelongsTo<Layout, $this>
     */
    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    /**
     * @return HasMany<Page, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Page::class, 'parent_id');
    }

    /**
     * Every page below this one, nearest first
     *
     * @return Collection<int, Page>
     */
    public function descendants(): Collection
    {
        $descendants = collect();
        $level = $this->children()->get();

        while ($level->isNotEmpty()) {
            $descendants = $descendants->merge($level);
            $level = Page::query()->whereIn('parent_id', $level->pluck('id'))->get();
        }

        return $descendants;
    }

    /**
     * This page's own part of the URL, e.g. "/team", or "/" for a blank slug
     */
    public function urlMain(): string
    {
        return '/'.$this->slug;
    }

    /**
     * The whole path: the parent's URL plus this page's part, e.g. "/about/team"
     */
    public function urlFull(?string $parentUrl = null): string
    {
        $parentUrl ??= $this->parent?->url?->url_full;

        if ($parentUrl === null || $parentUrl === '/') {
            return $this->urlMain();
        }

        return rtrim($parentUrl, '/').$this->urlMain();
    }
}

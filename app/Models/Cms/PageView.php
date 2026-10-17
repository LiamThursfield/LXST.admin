<?php

namespace App\Models\Cms;

use Database\Factories\Cms\PageViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A visit to something on the site (a page), recorded by the site through the API.
 * The visitor's IP is truncated and hashed before it gets here (VisitorPrivacy).
 *
 * @property int $id
 * @property string|null $viewable_type
 * @property int|null $viewable_id
 * @property string $url
 * @property string|null $referrer
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property string|null $visitor_hash
 * @property string|null $session_id
 * @property array<string, string|null>|null $source
 * @property array<string, string|null>|null $additional_data
 * @property Carbon $viewed_at
 */
class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    protected $table = 'cms_page_views';

    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'url',
        'referrer',
        'user_agent',
        'ip_address',
        'visitor_hash',
        'session_id',
        'source',
        'additional_data',
        'viewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'source' => 'array',
            'additional_data' => 'array',
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function viewable(): MorphTo
    {
        return $this->morphTo();
    }
}

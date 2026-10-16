<?php

namespace App\Models\Cms;

use App\Data\Cms\MenuItemData;
use Database\Factories\Cms\MenuFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\DataCollection;

/**
 * A navigation menu: a tree of items, each linking to a page or a custom URL. Templates embed a
 * menu with a `cms_menu` field.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property DataCollection<int, MenuItemData>|null $menu_items
 */
class Menu extends Model
{
    /** @use HasFactory<MenuFactory> */
    use HasFactory;

    protected $table = 'cms_menus';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'menu_items',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'menu_items' => DataCollection::class.':'.MenuItemData::class,
        ];
    }

    /**
     * The ids of every page the menu links to, at any level
     *
     * @return list<int>
     */
    public function pageIds(): array
    {
        $ids = [];
        $walk = function (iterable $items) use (&$walk, &$ids): void {
            foreach ($items as $item) {
                if ($item->link_type === MenuItemData::LINK_PAGE && $item->page_id !== null) {
                    $ids[] = $item->page_id;
                }

                $walk($item->children);
            }
        };

        $walk($this->menu_items ?? []);

        return array_values(array_unique($ids));
    }
}

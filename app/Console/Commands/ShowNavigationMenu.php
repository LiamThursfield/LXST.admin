<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Navigation\Data\MenuChildItem;
use App\Services\Navigation\Data\MenuItem;
use App\Services\Navigation\Enums\MenuItemType;
use App\Services\Navigation\Exceptions\MenuKeyDoesNotExistException;
use App\Services\Navigation\NavigationRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

use function Laravel\Prompts\select;

class ShowNavigationMenu extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'navigation:show-menu {menu?} {user?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Displays a tree view of the requested navigation menu';

    /**
     * Execute the console command.
     */
    public function handle(NavigationRegistry $registry): int
    {
        $menuKey = $this->getMenuKey($registry);
        if ($menuKey === null) {
            return static::FAILURE;
        }

        $userId = $this->argument('user');

        $user = null;
        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found.");

                return static::FAILURE;
            }
        }

        try {
            $sections = $registry->resolveForUser($menuKey, $user);
        } catch (MenuKeyDoesNotExistException $e) {
            $this->error("Menu with key '{$menuKey}' does not exist.");

            return static::FAILURE;
        }

        $this->info("Menu: {$menuKey}");

        $allItems = collect();
        foreach ($sections as $sectionItems) {
            foreach ($sectionItems as $item) {
                $allItems->push($item);
            }
        }

        $itemCount = $allItems->count();
        $itemIndex = 0;

        foreach ($allItems as $item) {
            $itemIndex++;
            $isLastItem = $itemIndex === $itemCount;

            $this->renderItem($item, '', $isLastItem);
        }

        return static::SUCCESS;
    }

    /**
     * Returns the key of the menu to show
     * - grabbed from the argument if set
     * - or via a select prompt if not
     */
    protected function getMenuKey(NavigationRegistry $registry): ?string
    {
        $menuKey = (string) $this->argument('menu');

        if (! $menuKey) {
            $registeredKeys = $registry->registeredMenuKeys();
            if ($registeredKeys->isEmpty()) {
                $this->error('No menus are registered.');

                return null;
            }

            $menuKey = (string) select(
                label: 'Which menu would you like to view?',
                options: $registeredKeys->toArray()
            );
        }

        return $menuKey;
    }

    /**
     * Render an individual item to the console
     */
    protected function renderItem(MenuItem|MenuChildItem $item, string $prefix, bool $isLast): void
    {
        $connector = $isLast ? '└── ' : '├── ';
        $this->line($prefix.$connector.$this->itemRowText($item));

        $newPrefix = $prefix.($isLast ? '    ' : '│   ');

        $children = $item->children ?? collect();
        $childCount = $children->count();
        $childIndex = 0;

        foreach ($children as $child) {
            $childIndex++;
            $childIsLast = $childIndex === $childCount;
            $this->renderItem($child, $newPrefix, $childIsLast);
        }
    }

    /**
     * Return the text that should be shown for the given item
     */
    protected function itemRowText(MenuItem|MenuChildItem $item): string
    {
        $labelParts = [
            '['.Str::padLeft((string) $item->sortOrder, 3, '0').']',
        ];

        $labelParts[] = $item->label != null ? $item->label : $item->key;

        if (get_class($item) === MenuItem::class) {
            $labelParts[] = '('.($item->type != null ? $item->type->value : MenuItemType::LINK->value).')';
        }

        $transformed = $item->toArray();
        $to = Arr::get($transformed, 'to');
        if ($to != null) {
            $labelParts[] = '-> '.$to;
        }

        return Arr::join($labelParts, ' ');
    }
}

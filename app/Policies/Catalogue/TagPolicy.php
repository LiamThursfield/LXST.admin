<?php

namespace App\Policies\Catalogue;

use App\Models\Catalogue\Tag;
use App\Models\User;
use App\Services\Authorisation\Enums\CataloguePermission;

class TagPolicy
{
    /**
     * Whether the user can list catalogue tags.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CataloguePermission::ViewCatalogues);
    }

    /**
     * Whether the user can create catalogue tags, including adding the built-in ones.
     */
    public function create(User $user): bool
    {
        return $user->can(CataloguePermission::ManageCatalogues);
    }

    /**
     * Whether the user can update the given tag.
     */
    public function update(User $user, Tag $tag): bool
    {
        return $user->can(CataloguePermission::ManageCatalogues);
    }

    /**
     * Whether the user can delete the given tag. It's taken off every item.
     */
    public function delete(User $user, Tag $tag): bool
    {
        return $user->can(CataloguePermission::ManageCatalogues);
    }
}

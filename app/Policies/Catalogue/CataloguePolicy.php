<?php

namespace App\Policies\Catalogue;

use App\Models\Catalogue\Catalogue;
use App\Models\User;
use App\Services\Authorisation\Enums\CataloguePermission;

class CataloguePolicy
{
    /**
     * Whether the user can list catalogues.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(CataloguePermission::ViewCatalogues);
    }

    /**
     * Whether the user can view the given catalogue.
     */
    public function view(User $user, Catalogue $catalogue): bool
    {
        return $user->can(CataloguePermission::ViewCatalogues);
    }

    /**
     * Whether the user can create catalogues.
     */
    public function create(User $user): bool
    {
        return $user->can(CataloguePermission::ManageCatalogues);
    }

    /**
     * Whether the user can update the given catalogue, including its sections and items.
     */
    public function update(User $user, Catalogue $catalogue): bool
    {
        return $user->can(CataloguePermission::ManageCatalogues);
    }

    /**
     * Whether the user can mark the catalogue's items sold out and change their stock.
     */
    public function updateStock(User $user, Catalogue $catalogue): bool
    {
        return $user->can(CataloguePermission::ManageStock);
    }

    /**
     * Whether the user can delete the given catalogue, with its sections and items.
     */
    public function delete(User $user, Catalogue $catalogue): bool
    {
        return $user->can(CataloguePermission::ManageCatalogues);
    }
}

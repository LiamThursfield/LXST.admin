<?php

namespace App\Services\Authorisation\Enums;

enum CataloguePermission: string
{
    case ViewCatalogues = 'catalogue.view-catalogues';
    case ManageCatalogues = 'catalogue.manage-catalogues';

    // Marking items sold out and counting stock, without changing anything else
    case ManageStock = 'catalogue.manage-stock';
}

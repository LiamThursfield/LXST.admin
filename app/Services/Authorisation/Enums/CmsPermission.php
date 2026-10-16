<?php

namespace App\Services\Authorisation\Enums;

/**
 * Legacy split CMS work into content (`cms`) and structure (`cms_advanced`). Content is pages and
 * their copy; structure is what shapes the site, e.g. templates, layouts and redirects.
 */
enum CmsPermission: string
{
    case ViewContent = 'cms.view-content';
    case ManageContent = 'cms.manage-content';

    case ViewStructure = 'cms.view-structure';
    case ManageStructure = 'cms.manage-structure';
}

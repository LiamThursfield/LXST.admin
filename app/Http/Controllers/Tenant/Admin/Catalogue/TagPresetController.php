<?php

namespace App\Http\Controllers\Tenant\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Catalogue\TagPresetRequest;
use App\Services\Catalogue\CatalogueTags;
use Illuminate\Http\RedirectResponse;

/**
 * Adding a group of built-in tags, such as the 14 allergens
 */
class TagPresetController extends Controller
{
    public function store(TagPresetRequest $request, CatalogueTags $presets): RedirectResponse
    {
        $group = $request->string('group')->toString();
        $added = $presets->add($group);

        return back()->with('success', trans_choice('catalogue.tags.flash.presets_added', $added, [
            'group' => __("catalogue.presets.groups.{$group}"),
        ]));
    }
}

<?php

namespace App\Http\Controllers\Tenant\Admin\Catalogue;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\Admin\Catalogue\ItemStockRequest;
use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Marking an item sold out and counting its stock, from the catalogue's page, for staff who can do
 * that but not change the catalogue
 */
class ItemStockController extends Controller
{
    public function update(ItemStockRequest $request, Catalogue $catalogue, Item $item): RedirectResponse
    {
        abort_unless($item->section->catalogue_id === $catalogue->id, 404);

        DB::transaction(function () use ($request, $item) {
            $item->update($request->safe()->only(['is_available', 'stock']));

            /** @var list<array{id: int, stock: int}> $variations */
            $variations = $request->validated('variations', []);

            foreach ($variations as $variation) {
                $item->variations()->whereKey($variation['id'])->update(['stock' => $variation['stock']]);
            }
        });

        return back()->with('success', __('catalogue.stock.flash.updated', ['name' => $item->name]));
    }
}

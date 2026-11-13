<?php

namespace App\Http\Requests\Tenant\Admin\Catalogue;

use App\Models\Catalogue\Catalogue;
use App\Models\Catalogue\Item;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Marking an item sold out or back on, and counting its stock, without changing anything else
 */
class ItemStockRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $catalogue = $this->route('catalogue');

        return $catalogue instanceof Catalogue && ($this->user()?->can('updateStock', $catalogue) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $item = $this->item();
        $stock = ['integer', 'min:0', 'max:1000000'];

        return [
            'is_available' => ['sometimes', 'boolean'],
            'stock' => [Rule::excludeIf(! $item->tracks_stock || $item->variations()->exists()), 'sometimes', ...$stock],
            'variations' => [Rule::excludeIf(! $item->tracks_stock), 'sometimes', 'array'],
            'variations.*.id' => ['required', 'integer', 'distinct', Rule::in($item->variations()->pluck('id')->all())],
            'variations.*.stock' => ['required', ...$stock],
        ];
    }

    public function item(): Item
    {
        /** @var Item */
        return $this->route('item');
    }
}

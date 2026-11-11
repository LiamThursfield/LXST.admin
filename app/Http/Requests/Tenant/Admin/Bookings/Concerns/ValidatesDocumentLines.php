<?php

namespace App\Http\Requests\Tenant\Admin\Bookings\Concerns;

/**
 * The lines on a quote or an invoice, with prices entered in pounds. A negative price is a discount.
 */
trait ValidatesDocumentLines
{
    /**
     * @return array<string, mixed>
     */
    protected function lineRules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:100000'],
            'lines.*.unit_price' => ['required', 'numeric', 'decimal:0,2', 'min:-1000000', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function lineAttributes(): array
    {
        return [
            'lines.*.description' => __('bookings.documents.fields.description'),
            'lines.*.quantity' => __('bookings.documents.fields.quantity'),
            'lines.*.unit_price' => __('bookings.documents.fields.unit_price'),
        ];
    }

    /**
     * The lines with prices in pence, through round() as 19.99 * 100 is just under 1999 as a float
     *
     * @return list<array{description: string, quantity: string, unit_price: int}>
     */
    public function lines(): array
    {
        /** @var list<array{description: string, quantity: string|int|float, unit_price: string|int|float}> $lines */
        $lines = $this->validated('lines');

        return array_map(fn (array $line) => [
            'description' => (string) $line['description'],
            'quantity' => (string) $line['quantity'],
            'unit_price' => (int) round((float) $line['unit_price'] * 100),
        ], $lines);
    }
}

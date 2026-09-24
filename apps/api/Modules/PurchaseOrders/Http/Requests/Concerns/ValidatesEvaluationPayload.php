<?php

namespace Modules\PurchaseOrders\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Shared evaluation payload rules for Store/Update evaluation requests.
 * The matrix is always submitted whole: items + quotations with one line
 * per item per quotation, at least one winning (selected) line per item,
 * and no duplicate suppliers within one evaluation.
 */
trait ValidatesEvaluationPayload
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function evaluationRules(): array
    {
        return [
            'recommendation_basis' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_code' => ['required', 'string', 'max:64'],
            'items.*.description' => ['required', 'string'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.uom' => ['required', 'string', 'max:32'],
            'quotations' => ['required', 'array', 'min:2'],
            'quotations.*.supplier_code' => ['required', 'string', 'max:64'],
            'quotations.*.supplier_name' => ['required', 'string', 'max:255'],
            'quotations.*.supplier_phone' => ['nullable', 'string', 'max:255'],
            'quotations.*.supplier_address' => ['nullable', 'string', 'max:255'],
            'quotations.*.discount' => ['nullable', 'numeric', 'min:0'],
            'quotations.*.vat' => ['nullable', 'numeric', 'min:0'],
            'quotations.*.price' => ['nullable', 'string', 'max:255'],
            'quotations.*.quality' => ['nullable', 'string', 'max:255'],
            'quotations.*.lead_time' => ['nullable', 'string'],
            'quotations.*.warranty' => ['nullable', 'string'],
            'quotations.*.payment_terms' => ['nullable', 'string'],
            'quotations.*.lines' => ['required', 'array', 'min:1'],
            'quotations.*.lines.*.item_index' => ['required', 'integer', 'min:0'],
            'quotations.*.lines.*.brand' => ['nullable', 'string', 'max:255'],
            'quotations.*.lines.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'quotations.*.lines.*.is_selected' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $this->validateEvaluationMatrix($validator);
        });
    }

    protected function validateEvaluationMatrix(Validator $validator): void
    {
        $items = $this->input('items');
        $quotations = $this->input('quotations');

        if (! is_array($items) || $items === [] || ! is_array($quotations)) {
            return;
        }

        $itemCount = count($items);
        $selectedCount = array_fill(0, $itemCount, 0);
        $seenSuppliers = [];

        foreach ($quotations as $quotationIndex => $quotation) {
            if (! is_array($quotation)) {
                continue;
            }

            $supplierCode = mb_strtolower(trim((string) ($quotation['supplier_code'] ?? '')));

            if ($supplierCode !== '') {
                if (isset($seenSuppliers[$supplierCode])) {
                    $validator->errors()->add(
                        "quotations.{$quotationIndex}.supplier_code",
                        'Each supplier may appear only once per evaluation.',
                    );
                } else {
                    $seenSuppliers[$supplierCode] = $quotationIndex;
                }
            }

            $lines = is_array($quotation['lines'] ?? null) ? $quotation['lines'] : [];
            $seen = [];

            foreach ($lines as $lineIndex => $line) {
                if (! is_array($line)) {
                    continue;
                }

                $itemIndex = filter_var($line['item_index'] ?? null, FILTER_VALIDATE_INT);

                if ($itemIndex === false || $itemIndex < 0 || $itemIndex >= $itemCount) {
                    $validator->errors()->add(
                        "quotations.{$quotationIndex}.lines.{$lineIndex}.item_index",
                        'This line references an unknown item.',
                    );

                    continue;
                }

                if (isset($seen[$itemIndex])) {
                    $validator->errors()->add(
                        "quotations.{$quotationIndex}.lines",
                        'Each item may appear only once per quotation.',
                    );
                }
                $seen[$itemIndex] = true;

                if (filter_var($line['is_selected'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    $selectedCount[$itemIndex]++;
                }
            }

            $missing = array_diff(range(0, $itemCount - 1), array_keys($seen));
            if ($missing !== []) {
                $validator->errors()->add(
                    "quotations.{$quotationIndex}.lines",
                    'Every item must be priced in every quotation.',
                );
            }
        }

        foreach ($selectedCount as $index => $count) {
            if ($count === 0) {
                $validator->errors()->add(
                    "items.{$index}",
                    'Each item needs at least one selected (winning) quotation.',
                );
            } elseif ($count > 1) {
                $validator->errors()->add(
                    "items.{$index}",
                    'Each item may have only one selected (winning) quotation.',
                );
            }
        }
    }

    /**
     * Column-bound payload for the service (matrix arrays intact).
     *
     * @return array<string, mixed>
     */
    public function evaluationData(): array
    {
        return [
            'recommendation_basis' => $this->validated('recommendation_basis'),
            'items' => array_values($this->validated('items', [])),
            'quotations' => array_values($this->validated('quotations', [])),
        ];
    }
}

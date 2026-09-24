<?php

namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMergeVariationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $entityId = $this->user()?->entity_id;

        return [
            'parentId' => [
                'required',
                'string',
                Rule::exists('products', 'id')->where('entity_id', $entityId),
            ],
            'templateIds' => ['required', 'array', 'min:1'],
            'templateIds.*' => [
                Rule::exists('variation_templates', 'id')->where('entity_id', $entityId),
            ],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.productId' => [
                'required',
                'string',
                Rule::exists('products', 'id')->where('entity_id', $entityId),
            ],
            'assignments.*.values' => ['required', 'array', 'min:1'],
            'assignments.*.values.*' => ['required', 'string', 'max:255'],
        ];
    }
}

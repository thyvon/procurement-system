<?php

namespace Modules\Products\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariationTemplateRequest extends FormRequest
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
        $templateId = $this->route('variationTemplate');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('variation_templates', 'name')
                    ->where('entity_id', $entityId)
                    ->ignore($templateId),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'array', 'min:1'],
            'options.*.id' => ['nullable', Rule::exists('variation_template_options', 'id')],
            'options.*.value' => ['required_with:options', 'string', 'max:255'],
            'options.*.sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

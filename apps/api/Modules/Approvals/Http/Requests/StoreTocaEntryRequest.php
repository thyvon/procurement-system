<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Approvals\Http\Requests\Concerns\ValidatesTocaAuthority;
use Modules\Approvals\Services\ApprovalSubjectRegistry;

class StoreTocaEntryRequest extends FormRequest
{
    use ValidatesTocaAuthority;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('toca_entries', 'name')
                    ->where('entity_id', $this->user()?->entity_id)
                    ->whereNull('deleted_at'),
            ],
            'subject_type' => ['required', 'string', 'max:64', Rule::in(ApprovalSubjectRegistry::types())],
            'step_key' => $this->stepKeyRules(),
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `subject_id` may be omitted for a document that has not been created
     * yet; a draft `amount` is then required instead.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', 'max:64'],
            'subject_id' => ['sometimes', 'nullable', 'string', 'max:26'],
            'amount' => [
                Rule::requiredIf(fn (): bool => ! $this->filled('subject_id')),
                'nullable',
                'numeric',
                'min:0',
            ],
        ];
    }
}

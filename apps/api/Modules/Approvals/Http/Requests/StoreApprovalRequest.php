<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Step positions are validated against the resolved flow in the service;
     * this request only guarantees the payload shape.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', 'max:64'],
            'subject_id' => ['required', 'string', 'max:26'],
            'assignees' => ['required', 'array'],
            'assignees.*' => ['integer', 'min:1'],
        ];
    }
}

<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalActionRequest extends FormRequest
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
        return [
            'action' => ['required', Rule::in(['approve', 'reject', 'return'])],
            'comment' => [
                'required_if:action,reject',
                'required_if:action,return',
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}

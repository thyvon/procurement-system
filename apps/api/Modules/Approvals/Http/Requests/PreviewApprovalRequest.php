<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PreviewApprovalRequest extends FormRequest
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
            'subject_type' => ['required', 'string', 'max:64'],
            'subject_id' => ['required', 'string', 'max:26'],
        ];
    }
}

<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApprovalDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Positions are not validated against the resolved flow here — the draft
     * is preparation data; submit still validates every assignee authoritatively.
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

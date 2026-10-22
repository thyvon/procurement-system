<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Positions are not validated against the resolved flow here — the draft
     * is preparation data; submit still validates every assignee authoritatively.
     * Each position accepts one user id or several (co-signed steps).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', 'max:64'],
            'subject_id' => ['required', 'string', 'max:26'],
            'assignees' => ['required', 'array'],
            'assignees.*' => ['required', Rule::anyOf(['integer', 'array'])],
            'assignees.*.*' => ['integer', 'min:1'],
        ];
    }
}

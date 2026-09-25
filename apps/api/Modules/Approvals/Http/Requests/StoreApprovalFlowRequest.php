<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Services\ApprovalSubjectRegistry;

class StoreApprovalFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The flow `code` is generated server-side from the name; positions come
     * from the array order of `steps`.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject_type' => ['required', 'string', 'max:64', Rule::in(ApprovalSubjectRegistry::types())],
            'name' => ['required', 'string', 'max:255'],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.key' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'steps.*.label' => ['required', 'string', 'max:255'],
            'steps.*.action_mode' => ['required', 'string', Rule::in([ApprovalStep::MODE_RECORD, ApprovalStep::MODE_DECIDE])],
            'steps.*.allowed_actions' => ['nullable', 'array'],
            'steps.*.allowed_actions.*' => ['string', Rule::in(['approve', 'reject', 'return'])],
        ];
    }
}

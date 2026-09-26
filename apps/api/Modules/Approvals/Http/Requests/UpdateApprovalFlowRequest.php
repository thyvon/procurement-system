<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Approvals\Models\ApprovalStep;

class UpdateApprovalFlowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sending `steps` replaces the flow's ordered steps wholesale; omitting
     * it leaves them untouched. Cross-record rules (amount-band integrity,
     * step semantics) live in ApprovalFlowService.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'steps' => ['sometimes', 'array', 'min:1'],
            'steps.*.key' => ['required', 'string', 'max:32', 'regex:/^[a-z0-9][a-z0-9_-]*$/'],
            'steps.*.label' => ['required', 'string', 'max:255'],
            'steps.*.action_mode' => ['required', 'string', Rule::in([ApprovalStep::MODE_RECORD, ApprovalStep::MODE_DECIDE])],
            'steps.*.allowed_actions' => ['nullable', 'array'],
            'steps.*.allowed_actions.*' => ['string', Rule::in(['approve', 'reject', 'return'])],
            'steps.*.show_on_print' => ['sometimes', 'boolean'],
        ];
    }
}

<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Approvals\Services\ApprovalSubjectRegistry;

class StoreTocaEntryRequest extends FormRequest
{
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
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'subject_type' => ['required', 'string', 'max:64', Rule::in(ApprovalSubjectRegistry::types())],
            'min_amount' => ['required', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $min = $this->input('min_amount');
            $max = $this->input('max_amount');

            if ($min !== null && $max !== null && is_numeric($min) && is_numeric($max) && (float) $max < (float) $min) {
                $validator->errors()->add('max_amount', 'The maximum amount must be greater than or equal to the minimum amount.');
            }
        });
    }
}

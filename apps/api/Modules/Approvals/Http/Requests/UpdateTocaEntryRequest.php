<?php

namespace Modules\Approvals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Approvals\Services\ApprovalSubjectRegistry;

class UpdateTocaEntryRequest extends FormRequest
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
            'user_id' => ['sometimes', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'subject_type' => ['sometimes', 'string', 'max:64', Rule::in(ApprovalSubjectRegistry::types())],
            'min_amount' => ['sometimes', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $min = $this->input('min_amount');
            $max = $this->input('max_amount');

            if ($max === null || ! is_numeric($max)) {
                return;
            }

            // Fall back to the stored row when the payload only sends the maximum.
            if ($min === null || ! is_numeric($min)) {
                $min = $this->route('entry')?->min_amount;
            }

            if ($min !== null && is_numeric($min) && (float) $max < (float) $min) {
                $validator->errors()->add('max_amount', 'The maximum amount must be greater than or equal to the minimum amount.');
            }
        });
    }
}

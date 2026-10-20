<?php

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Payments\Http\Requests\Concerns\ValidatesCashClearancePayload;

class UpdateCashClearanceRequest extends FormRequest
{
    use ValidatesCashClearancePayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Full-replace semantics: the line list is always submitted whole.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->cashClearanceRules();
    }
}

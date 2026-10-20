<?php

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Payments\Http\Requests\Concerns\ValidatesCashClearancePayload;

class StoreCashClearanceRequest extends FormRequest
{
    use ValidatesCashClearancePayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->cashClearanceRules();
    }
}

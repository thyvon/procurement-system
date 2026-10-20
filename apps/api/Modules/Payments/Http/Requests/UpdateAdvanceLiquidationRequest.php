<?php

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Payments\Http\Requests\Concerns\ValidatesAdvanceLiquidationPayload;

class UpdateAdvanceLiquidationRequest extends FormRequest
{
    use ValidatesAdvanceLiquidationPayload;

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
        return $this->advanceLiquidationRules();
    }
}

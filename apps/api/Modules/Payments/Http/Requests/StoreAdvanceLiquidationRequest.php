<?php

namespace Modules\Payments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Payments\Http\Requests\Concerns\ValidatesAdvanceLiquidationPayload;

class StoreAdvanceLiquidationRequest extends FormRequest
{
    use ValidatesAdvanceLiquidationPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->advanceLiquidationRules();
    }
}

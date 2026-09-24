<?php

namespace Modules\PurchaseOrders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\PurchaseOrders\Http\Requests\Concerns\ValidatesEvaluationPayload;

class StoreEvaluationRequest extends FormRequest
{
    use ValidatesEvaluationPayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->evaluationRules();
    }
}

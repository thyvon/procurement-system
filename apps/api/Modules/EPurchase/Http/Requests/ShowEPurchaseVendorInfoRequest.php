<?php

namespace Modules\EPurchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowEPurchaseVendorInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Upstream expects SupplierCode as the numeric vendor id (e.g. "17"), not the SUP-* code.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'supplier_code' => ['required', 'string', 'max:255'],
        ];
    }
}

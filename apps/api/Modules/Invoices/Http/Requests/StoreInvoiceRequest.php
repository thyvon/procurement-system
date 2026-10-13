<?php

namespace Modules\Invoices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Invoices\Http\Requests\Concerns\ValidatesInvoicePayload;

class StoreInvoiceRequest extends FormRequest
{
    use ValidatesInvoicePayload;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->invoiceRules();
    }
}

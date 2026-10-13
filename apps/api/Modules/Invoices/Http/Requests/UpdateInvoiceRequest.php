<?php

namespace Modules\Invoices\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Invoices\Http\Requests\Concerns\ValidatesInvoicePayload;

class UpdateInvoiceRequest extends FormRequest
{
    use ValidatesInvoicePayload;

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
        return $this->invoiceRules();
    }
}

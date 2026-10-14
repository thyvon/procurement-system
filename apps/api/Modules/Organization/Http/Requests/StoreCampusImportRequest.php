<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCampusImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Structural checks only — missing names are reported per-row by the
     * import service, not fatal for the file.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'max:5000'],
            'rows.*.name' => ['nullable', 'string'],
            'rows.*.status' => ['nullable', 'string'],
        ];
    }
}

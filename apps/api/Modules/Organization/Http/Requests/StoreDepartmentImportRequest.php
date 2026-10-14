<?php

namespace Modules\Organization\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Structural checks only — missing names/divisions are reported
     * per-row by the import service, not fatal for the file.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'max:5000'],
            'rows.*.name' => ['nullable', 'string'],
            'rows.*.divisions' => ['nullable', 'string'],
            'rows.*.status' => ['nullable', 'string'],
        ];
    }
}

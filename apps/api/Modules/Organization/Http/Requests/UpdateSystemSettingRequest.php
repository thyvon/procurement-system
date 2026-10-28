<?php

namespace Modules\Organization\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation is driven by the whitelist in config/system-settings.php:
     * unknown keys are rejected outright and every value is typed and
     * bounded before anything can reach the store.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $keys = (array) config('system-settings.'.((string) $this->route('group')).'.keys', []);

        $rules = [
            'values' => [
                'sometimes',
                'nullable',
                'array',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail) use ($keys): void {
                    if (! is_array($value)) {
                        return;
                    }

                    if (array_diff(array_keys($value), array_keys($keys)) !== []) {
                        $fail('The values contain keys that are not allowed.');
                    }
                },
            ],
        ];

        foreach ($keys as $key => $definition) {
            $rules["values.{$key}"] = match ($definition['type']) {
                'boolean' => ['sometimes', 'nullable', 'boolean'],
                'integer' => ['sometimes', 'nullable', 'integer', 'min:'.$definition['min'], 'max:'.$definition['max']],
                'url' => ['sometimes', 'nullable', 'string', 'url:http,https', 'max:'.$definition['max']],
                default => ['sometimes', 'nullable', 'string', 'max:'.$definition['max']],
            };
        }

        return $rules;
    }
}

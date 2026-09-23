<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Role $role */
        $role = $this->route('role');
        $isSystemAdmin = $role->name === 'admin';

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('roles', 'name')
                    ->where('guard_name', 'sanctum')
                    ->ignore($role->getKey()),
                function (string $attribute, mixed $value, \Closure $fail) use ($isSystemAdmin): void {
                    if ($isSystemAdmin && $value !== 'admin') {
                        $fail('The admin role cannot be renamed.');
                    }
                },
            ],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', Rule::exists('permissions', 'name')->where('guard_name', 'sanctum')],
        ];
    }
}

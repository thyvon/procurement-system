<?php

namespace Modules\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:10'],
            'is_active' => ['sometimes', 'boolean'],
            'entity_id' => ['sometimes', 'nullable', 'string', Rule::exists('entities', 'id')],
            'roles' => ['sometimes', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'name')->where('guard_name', 'sanctum')],
            'toca_entry_ids' => ['sometimes', 'array'],
            'toca_entry_ids.*' => [
                'string',
                Rule::exists('toca_entries', 'id')
                    ->where('entity_id', $this->user()?->entity_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function userData(): array
    {
        return [
            'name' => $this->string('name')->toString(),
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'is_active' => $this->boolean('is_active', true),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function roleNames(): array
    {
        return $this->input('roles') ?? ['staff'];
    }
}

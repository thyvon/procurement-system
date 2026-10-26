<?php

namespace Modules\Users\Http\Requests;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $userId = $this->route('user');

        $currentPasswordCheck = function (string $attribute, mixed $value, \Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            if (! Hash::check($value, (string) $this->user()?->password)) {
                $fail('The current password is incorrect.');
            }
        };

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'string', 'min:10'],
            'current_password' => $this->isSelfUpdate()
                ? ['required_with:password', 'string', $currentPasswordCheck]
                : ['sometimes', 'string', $currentPasswordCheck],
            'is_active' => ['sometimes', 'boolean'],
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
     * The current-password confirmation only applies to self-service password
     * changes; admins resetting another user's password cannot know it.
     */
    private function isSelfUpdate(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        return $actor instanceof User
            && $target instanceof Model
            && $actor->is($target);
    }
}

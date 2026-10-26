<?php

namespace Modules\EmployeeProfiles\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Modules\EmployeeProfiles\Repositories\EmployeeProfileRepositoryInterface;

class StoreEmployeeProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['card_id', 'position', 'campus_id', 'division_id', 'department_id', 'effective_from'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $target = $this->route('user');
        $target = $target instanceof User ? $target : null;
        $entityId = $target?->entity_id ?? $this->user()?->entity_id;

        $currentId = $target !== null
            ? app(EmployeeProfileRepositoryInterface::class)->currentFor($target)?->getKey()
            : null;

        return [
            'card_id' => [
                'sometimes',
                'nullable',
                'string',
                'max:64',
                Rule::unique('employee_profiles', 'card_id')
                    ->where('entity_id', $entityId)
                    ->where('is_current', true)
                    ->whereNull('deleted_at')
                    ->ignore($currentId),
            ],
            'position' => ['sometimes', 'nullable', 'string', 'max:255'],
            'campus_id' => ['sometimes', 'nullable', 'string', $this->lookupRule('campuses', $entityId)],
            'division_id' => ['sometimes', 'nullable', 'string', $this->lookupRule('divisions', $entityId)],
            'department_id' => ['sometimes', 'nullable', 'string', $this->lookupRule('departments', $entityId)],
            'effective_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Only the supplied keys are returned: omitted fields are inherited from
     * the current profile by the service rather than cleared.
     *
     * @return array<string, mixed>
     */
    public function profileData(): array
    {
        $data = [];

        foreach (['card_id', 'position', 'campus_id', 'division_id', 'department_id', 'effective_from'] as $key) {
            if ($this->exists($key)) {
                $data[$key] = $this->input($key);
            }
        }

        return $data;
    }

    private function lookupRule(string $table, ?string $entityId): Exists
    {
        return Rule::exists($table, 'id')
            ->where('entity_id', $entityId)
            ->whereNull('deleted_at');
    }
}

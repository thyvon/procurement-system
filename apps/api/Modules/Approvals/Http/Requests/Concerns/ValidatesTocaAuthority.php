<?php

namespace Modules\Approvals\Http\Requests\Concerns;

use App\Support\Context\EntityContext;
use Illuminate\Validation\Rule;

/**
 * Shared validation for Store/Update authority rows: the amount band may
 * not be inverted (falling back to the stored row when the payload only
 * sends the maximum) and the step key must name a step of a flow belonging
 * to the entry's subject type.
 */
trait ValidatesTocaAuthority
{
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $min = $this->input('min_amount');
            $max = $this->input('max_amount');

            if ($max === null || ! is_numeric($max)) {
                return;
            }

            // Fall back to the stored row when the payload only sends the maximum.
            if ($min === null || ! is_numeric($min)) {
                $min = $this->route('entry')?->min_amount;
            }

            if ($min !== null && is_numeric($min) && (float) $max < (float) $min) {
                $validator->errors()->add('max_amount', 'The maximum amount must be greater than or equal to the minimum amount.');
            }
        });
    }

    /**
     * The step key must name a step of a flow belonging to the entry's
     * subject type — catches typos and keys retired from the flows.
     *
     * @return array<int, mixed>
     */
    protected function stepKeyRules(): array
    {
        $subjectType = $this->input('subject_type') ?? $this->route('entry')?->subject_type;
        $entityId = app(EntityContext::class)->entityId();

        $rule = Rule::exists('approval_steps', 'key')
            ->whereNull('deleted_at')
            ->whereIn('approval_flow_id', function ($query) use ($subjectType): void {
                $query->select('id')
                    ->from('approval_flows')
                    ->whereNull('deleted_at')
                    ->whereIn('approval_setting_id', function ($query) use ($subjectType): void {
                        $query->select('id')
                            ->from('approval_settings')
                            ->whereNull('deleted_at')
                            ->where('subject_type', $subjectType);
                    });
            });

        if ($entityId !== null) {
            $rule->where('entity_id', $entityId);
        }

        return ['nullable', 'string', 'max:32', $rule];
    }
}

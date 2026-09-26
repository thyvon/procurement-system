<?php

namespace Modules\Approvals\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalRequest;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Organization\Models\Entity;

/**
 * Builds a pending request against a minimal factory workflow. Full
 * end-to-end setups (steps, TOCA, amounts) go through the API in tests.
 */
class ApprovalRequestFactory extends Factory
{
    protected $model = ApprovalRequest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $entity = Entity::query()->first()
            ?? Entity::query()->firstOrCreate(
                ['code' => 'MAIN'],
                ['name' => 'Main Organization', 'timezone' => 'UTC', 'locale' => 'en'],
            );

        $setting = ApprovalSetting::query()->firstOrCreate(
            ['entity_id' => $entity->getKey(), 'subject_type' => 'evaluation'],
            ['name' => 'Evaluation Approval', 'is_active' => true],
        );

        $flow = ApprovalFlow::query()->firstOrCreate(
            ['entity_id' => $entity->getKey(), 'code' => 'factory-flow'],
            [
                'approval_setting_id' => $setting->getKey(),
                'name' => 'Factory flow',
                'min_amount' => 0,
                'max_amount' => null,
                'is_active' => true,
            ],
        );

        return [
            'entity_id' => $entity->getKey(),
            'subject_type' => 'evaluation',
            'subject_id' => (string) Str::ulid(),
            'approval_setting_id' => $setting->getKey(),
            'approval_flow_id' => $flow->getKey(),
            'amount_snapshot' => 100,
            'status' => ApprovalRequest::STATUS_PENDING,
            'current_position' => 2,
            'snapshot' => [
                'documentCode' => 'EVAL-26-001',
                'flow' => [
                    'id' => $flow->getKey(),
                    'code' => $flow->code,
                    'name' => $flow->name,
                    'minAmount' => $flow->min_amount,
                    'maxAmount' => $flow->max_amount,
                ],
                'steps' => [
                    [
                        'position' => 1,
                        'key' => 'prepared',
                        'label' => 'Prepared By',
                        'actionMode' => 'record',
                        'allowedActions' => [],
                        'assigneeId' => null,
                        'assigneeName' => null,
                        'assigneePosition' => null,
                    ],
                    [
                        'position' => 2,
                        'key' => 'checked',
                        'label' => 'Checked By',
                        'actionMode' => 'decide',
                        'allowedActions' => ['approve', 'reject', 'return'],
                        'assigneeId' => null,
                        'assigneeName' => null,
                        'assigneePosition' => null,
                    ],
                ],
            ],
        ];
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'current_assignee_id' => $user->getKey(),
        ]);
    }
}

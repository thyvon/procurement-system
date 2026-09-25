<?php

namespace Modules\Approvals\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Models\TocaEntry;
use Modules\Organization\Models\Entity;

/**
 * Baseline approval configuration: the Evaluation workflow (two amount
 * bands) and placeholder TOCA authority rows. Runtime rows are created by
 * the API, never here.
 */
class ApprovalsDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $entity = Entity::query()->first();

        if ($entity === null) {
            return;
        }

        $admin = User::query()->where('email', 'admin@procurement.local')->first();

        $setting = ApprovalSetting::query()->firstOrCreate(
            ['entity_id' => $entity->getKey(), 'subject_type' => 'evaluation'],
            [
                'name' => 'Evaluation Approval',
                'is_active' => true,
                'created_by' => $admin?->getKey(),
                'updated_by' => $admin?->getKey(),
            ],
        );

        $standard = ApprovalFlow::query()->firstOrCreate(
            ['entity_id' => $entity->getKey(), 'code' => 'evaluation-standard'],
            [
                'approval_setting_id' => $setting->getKey(),
                'name' => 'Standard (0 – 1,000)',
                'min_amount' => 0,
                'max_amount' => 1000,
                'is_active' => true,
            ],
        );

        $highValue = ApprovalFlow::query()->firstOrCreate(
            ['entity_id' => $entity->getKey(), 'code' => 'evaluation-high-value'],
            [
                'approval_setting_id' => $setting->getKey(),
                'name' => 'High Value (1,001+)',
                'min_amount' => 1001,
                'max_amount' => null,
                'is_active' => true,
            ],
        );

        $this->seedSteps($entity->getKey(), $standard, [
            ['prepared', 'Prepared By', ApprovalStep::MODE_RECORD],
            ['acknowledged', 'Acknowledged By', ApprovalStep::MODE_DECIDE],
            ['checked', 'Checked By', ApprovalStep::MODE_DECIDE],
            ['approved', 'Approved By', ApprovalStep::MODE_DECIDE],
        ]);

        $this->seedSteps($entity->getKey(), $highValue, [
            ['prepared', 'Prepared By', ApprovalStep::MODE_RECORD],
            ['acknowledged', 'Acknowledged By', ApprovalStep::MODE_DECIDE],
            ['checked', 'Checked By', ApprovalStep::MODE_DECIDE],
            ['executive', 'Executive Approval', ApprovalStep::MODE_DECIDE],
            ['approved', 'Approved By', ApprovalStep::MODE_DECIDE],
        ]);

        $staff = User::query()->where('email', 'staff@procurement.local')->first();

        if ($admin !== null) {
            TocaEntry::query()->firstOrCreate(
                [
                    'entity_id' => $entity->getKey(),
                    'user_id' => $admin->getKey(),
                    'subject_type' => 'evaluation',
                    'min_amount' => 0,
                ],
                ['max_amount' => null, 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey()],
            );
        }

        if ($staff !== null) {
            TocaEntry::query()->firstOrCreate(
                [
                    'entity_id' => $entity->getKey(),
                    'user_id' => $staff->getKey(),
                    'subject_type' => 'evaluation',
                    'min_amount' => 0,
                ],
                ['max_amount' => 1000, 'created_by' => $admin?->getKey(), 'updated_by' => $admin?->getKey()],
            );
        }
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $definitions
     */
    private function seedSteps(string $entityId, ApprovalFlow $flow, array $definitions): void
    {
        if ($flow->steps()->exists()) {
            return;
        }

        foreach ($definitions as $index => [$key, $label, $mode]) {
            ApprovalStep::query()->create([
                'entity_id' => $entityId,
                'approval_flow_id' => $flow->getKey(),
                'position' => $index + 1,
                'key' => $key,
                'label' => $label,
                'action_mode' => $mode,
                'allowed_actions' => $mode === ApprovalStep::MODE_DECIDE
                    ? ['approve', 'reject', 'return']
                    : null,
            ]);
        }
    }
}

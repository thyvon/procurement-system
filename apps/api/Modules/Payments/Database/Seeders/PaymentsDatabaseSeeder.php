<?php

namespace Modules\Payments\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Approvals\Models\ApprovalFlow;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\ApprovalStep;
use Modules\Approvals\Models\TocaEntry;
use Modules\Organization\Models\Entity;

/**
 * Baseline approval configuration for the five payment subject types: one
 * standard flow (0 – null) and a placeholder TOCA authority row each.
 * Runtime rows are created by the API, never here.
 */
class PaymentsDatabaseSeeder extends Seeder
{
    /**
     * The subject types this module registers with approvals: their human
     * label (setting/TOCA names derive from it) and the steps of the
     * standard flow each one gets.
     *
     * @var array<int, array{subject_type: string, label: string, steps: array<int, array{0: string, 1: string, 2: string}>}>
     */
    private const SUBJECTS = [
        [
            'subject_type' => 'petty_cash_request',
            'label' => 'Petty Cash Request',
            'steps' => [
                ['requested', 'Requested By', ApprovalStep::MODE_RECORD],
                ['checked', 'Checked By', ApprovalStep::MODE_DECIDE],
                ['approved', 'Approved By', ApprovalStep::MODE_DECIDE],
                ['received', 'Received By', ApprovalStep::MODE_DECIDE],
            ],
        ],
        [
            'subject_type' => 'advance_request',
            'label' => 'Advance Request',
            'steps' => [
                ['requested', 'Requested By', ApprovalStep::MODE_RECORD],
                ['checked', 'Checked By', ApprovalStep::MODE_DECIDE],
                ['approved', 'Approved By', ApprovalStep::MODE_DECIDE],
            ],
        ],
        [
            'subject_type' => 'cash_request',
            'label' => 'Cash Request',
            'steps' => [
                ['requested', 'Requested By', ApprovalStep::MODE_RECORD],
                ['checked', 'Checked By', ApprovalStep::MODE_DECIDE],
                ['approved', 'Approved By', ApprovalStep::MODE_DECIDE],
            ],
        ],
        [
            'subject_type' => 'cash_clearance',
            'label' => 'Cash Clearance',
            'steps' => [
                ['prepared', 'Prepared By', ApprovalStep::MODE_RECORD],
                ['checked', 'Checked By', ApprovalStep::MODE_DECIDE],
                ['approved', 'Approved By', ApprovalStep::MODE_DECIDE],
            ],
        ],
        [
            'subject_type' => 'advance_liquidation',
            'label' => 'Advance Liquidation',
            'steps' => [
                ['prepared', 'Prepared By', ApprovalStep::MODE_RECORD],
                ['checked', 'Checked By', ApprovalStep::MODE_DECIDE],
                ['approved', 'Approved By', ApprovalStep::MODE_DECIDE],
            ],
        ],
    ];

    public function run(): void
    {
        $entity = Entity::query()->first();

        if ($entity === null) {
            return;
        }

        $admin = User::query()->where('email', 'admin@procurement.local')->first();

        foreach (self::SUBJECTS as $subject) {
            $setting = ApprovalSetting::query()->firstOrCreate(
                ['entity_id' => $entity->getKey(), 'subject_type' => $subject['subject_type']],
                [
                    'name' => "{$subject['label']} Approval",
                    'is_active' => true,
                    'created_by' => $admin?->getKey(),
                    'updated_by' => $admin?->getKey(),
                ],
            );

            $standard = ApprovalFlow::query()->firstOrCreate(
                ['entity_id' => $entity->getKey(), 'code' => "{$subject['subject_type']}-standard"],
                [
                    'approval_setting_id' => $setting->getKey(),
                    'name' => 'Standard (0 – null)',
                    'min_amount' => 0,
                    'max_amount' => null,
                    'is_active' => true,
                ],
            );

            $this->seedSteps($entity->getKey(), $standard, $subject['steps']);

            if ($admin !== null) {
                $adminEntry = TocaEntry::query()->firstOrCreate(
                    [
                        'entity_id' => $entity->getKey(),
                        'name' => "{$subject['label']} unlimited",
                        'subject_type' => $subject['subject_type'],
                        'min_amount' => 0,
                    ],
                    ['max_amount' => null, 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey()],
                );

                $adminEntry->users()->syncWithoutDetaching([$admin->getKey()]);
            }
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

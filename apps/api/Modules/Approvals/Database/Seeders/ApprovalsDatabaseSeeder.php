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
 * bands), one standard flow per payment document type, and placeholder TOCA
 * authority rows. Runtime rows are created by the API, never here.
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
        $entityId = $entity->getKey();

        $this->seedFlows($entityId, $admin?->getKey(), 'evaluation', 'Evaluation Approval', [
            [
                'code' => 'evaluation-standard',
                'name' => 'Standard (0 – 1,000)',
                'min_amount' => 0,
                'max_amount' => 1000,
                'steps' => [
                    ['prepared', 'Prepared By', 'រៀបចំដោយ', ApprovalStep::MODE_RECORD],
                    ['acknowledged', 'Acknowledged By', 'ទទួលស្គាល់', ApprovalStep::MODE_DECIDE],
                    ['checked', 'Checked By', 'ត្រួតពិនិត្យ', ApprovalStep::MODE_DECIDE],
                    ['approved', 'Approved By', 'យល់ព្រម', ApprovalStep::MODE_DECIDE],
                ],
            ],
            [
                'code' => 'evaluation-high-value',
                'name' => 'High Value (1,001+)',
                'min_amount' => 1001,
                'max_amount' => null,
                'steps' => [
                    ['prepared', 'Prepared By', 'រៀបចំដោយ', ApprovalStep::MODE_RECORD],
                    ['acknowledged', 'Acknowledged By', 'ទទួលស្គាល់', ApprovalStep::MODE_DECIDE],
                    ['checked', 'Checked By', 'ត្រួតពិនិត្យ', ApprovalStep::MODE_DECIDE],
                    ['executive', 'Executive Approval', 'សម្រេចប្រធាន', ApprovalStep::MODE_DECIDE],
                    ['approved', 'Approved By', 'យល់ព្រម', ApprovalStep::MODE_DECIDE],
                ],
            ],
        ]);

        $this->seedFlows($entityId, $admin?->getKey(), 'petty_cash_request', 'Petty Cash Approval', [
            [
                'code' => 'petty-cash-standard',
                'name' => 'Standard',
                'min_amount' => 0,
                'max_amount' => null,
                'steps' => [
                    ['requested', 'Requested by', 'អ្នកសុំ', ApprovalStep::MODE_RECORD],
                    ['checked', 'Checked by', 'តម្រូវឲ្យពិនិត្យ', ApprovalStep::MODE_DECIDE],
                    ['approved', 'Approved by', 'អ្នកយល់ព្រម', ApprovalStep::MODE_DECIDE],
                    ['received', 'Received by', 'អ្នកទទួល', ApprovalStep::MODE_DECIDE],
                ],
            ],
        ]);

        $this->seedFlows($entityId, $admin?->getKey(), 'advance_request', 'Advance Approval', [
            [
                'code' => 'advance-standard',
                'name' => 'Standard',
                'min_amount' => 0,
                'max_amount' => null,
                'steps' => [
                    ['prepared', 'Prepared by', 'រៀបចំដោយ', ApprovalStep::MODE_RECORD],
                    ['reviewed', 'Reviewed by', 'ពិនិត្យដោយ', ApprovalStep::MODE_DECIDE],
                    ['approved', 'Approved by', 'យល់ព្រម', ApprovalStep::MODE_DECIDE],
                ],
            ],
        ]);

        $this->seedFlows($entityId, $admin?->getKey(), 'cash_request', 'Cash Request Approval', [
            [
                'code' => 'cash-standard',
                'name' => 'Standard',
                'min_amount' => 0,
                'max_amount' => null,
                'steps' => [
                    ['prepared', 'Prepared by', 'រៀបចំដោយ', ApprovalStep::MODE_RECORD],
                    ['reviewed', 'Reviewed by', 'ពិនិត្យដោយ', ApprovalStep::MODE_DECIDE],
                    ['approved', 'Approved by', 'យល់ព្រម', ApprovalStep::MODE_DECIDE],
                ],
            ],
        ]);

        $this->seedFlows($entityId, $admin?->getKey(), 'cash_clearance', 'Cash Clearance Approval', [
            [
                'code' => 'clearance-standard',
                'name' => 'Standard',
                'min_amount' => 0,
                'max_amount' => null,
                'steps' => [
                    ['prepared', 'Prepared by', 'អ្នករៀបចំ', ApprovalStep::MODE_RECORD],
                    ['reviewed', 'Reviewed by', 'អ្នកត្រួតពិនិត្យ', ApprovalStep::MODE_DECIDE],
                    ['approved', 'Approved by', 'អ្នកយល់ព្រម', ApprovalStep::MODE_DECIDE],
                ],
            ],
        ]);

        $this->seedFlows($entityId, $admin?->getKey(), 'advance_liquidation', 'Advance Liquidation Approval', [
            [
                'code' => 'liquidation-standard',
                'name' => 'Standard',
                'min_amount' => 0,
                'max_amount' => null,
                'steps' => [
                    ['prepared', 'Prepared by', 'អ្នករៀបចំ', ApprovalStep::MODE_RECORD],
                    ['reviewed', 'Reviewed by', 'អ្នកត្រួតពិនិត្យ', ApprovalStep::MODE_DECIDE],
                    ['approved', 'Approved by', 'អ្នកយល់ព្រម', ApprovalStep::MODE_DECIDE],
                ],
            ],
        ]);

        $staff = User::query()->where('email', 'staff@procurement.local')->first();

        if ($admin !== null) {
            $adminEntry = TocaEntry::query()->firstOrCreate(
                [
                    'entity_id' => $entity->getKey(),
                    'name' => 'Evaluation unlimited',
                    'subject_type' => 'evaluation',
                    'min_amount' => 0,
                ],
                ['max_amount' => null, 'created_by' => $admin->getKey(), 'updated_by' => $admin->getKey()],
            );

            $adminEntry->users()->syncWithoutDetaching([$admin->getKey()]);
        }

        // Demo of a step-scoped authority entry: staff may only acknowledge.
        if ($staff !== null) {
            $staffEntry = TocaEntry::query()->firstOrCreate(
                [
                    'entity_id' => $entity->getKey(),
                    'name' => 'Evaluation up to 1,000',
                    'subject_type' => 'evaluation',
                    'min_amount' => 0,
                ],
                ['max_amount' => 1000, 'created_by' => $admin?->getKey(), 'updated_by' => $admin?->getKey()],
            );

            $staffEntry->users()->syncWithoutDetaching([$staff->getKey()]);

            if ($staffEntry->step_key === null) {
                $staffEntry->update(['step_key' => 'acknowledged']);
            }
        }
    }

    /**
     * @param  array<int, array{code: string, name: string, min_amount: int, max_amount: ?int, steps: array<int, array{0: string, 1: string, 2: ?string, 3: string}>}>  $flows
     */
    private function seedFlows(string $entityId, ?int $adminId, string $subjectType, string $settingName, array $flows): void
    {
        $setting = ApprovalSetting::query()->firstOrCreate(
            ['entity_id' => $entityId, 'subject_type' => $subjectType],
            [
                'name' => $settingName,
                'is_active' => true,
                'created_by' => $adminId,
                'updated_by' => $adminId,
            ],
        );

        foreach ($flows as $flowDefinition) {
            $flow = ApprovalFlow::query()->firstOrCreate(
                ['entity_id' => $entityId, 'code' => $flowDefinition['code']],
                [
                    'approval_setting_id' => $setting->getKey(),
                    'name' => $flowDefinition['name'],
                    'min_amount' => $flowDefinition['min_amount'],
                    'max_amount' => $flowDefinition['max_amount'],
                    'is_active' => true,
                ],
            );

            $this->seedSteps($entityId, $flow, $flowDefinition['steps']);
        }
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: ?string, 3: string}>  $definitions
     */
    private function seedSteps(string $entityId, ApprovalFlow $flow, array $definitions): void
    {
        if ($flow->steps()->exists()) {
            return;
        }

        foreach ($definitions as $index => [$key, $label, $labelKh, $mode]) {
            ApprovalStep::query()->create([
                'entity_id' => $entityId,
                'approval_flow_id' => $flow->getKey(),
                'position' => $index + 1,
                'key' => $key,
                'label' => $label,
                'label_kh' => $labelKh,
                'action_mode' => $mode,
                'allowed_actions' => $mode === ApprovalStep::MODE_DECIDE
                    ? ['approve', 'reject', 'return']
                    : null,
            ]);
        }
    }
}

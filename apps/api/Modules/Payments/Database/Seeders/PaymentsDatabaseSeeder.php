<?php

namespace Modules\Payments\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Approvals\Models\ApprovalSetting;
use Modules\Approvals\Models\TocaEntry;
use Modules\Organization\Models\Entity;

/**
 * Approval settings and placeholder TOCA authority rows for the five payment
 * subject types. Flow definitions live in the Approvals module's seeder —
 * seeding them here too would create a second active open-ended band and
 * block every flow save in that setting. Runtime rows are created by the API,
 * never here.
 */
class PaymentsDatabaseSeeder extends Seeder
{
    /**
     * The subject types this module registers with approvals: their human
     * label (setting/TOCA names derive from it).
     *
     * @var array<int, array{subject_type: string, label: string}>
     */
    private const SUBJECTS = [
        ['subject_type' => 'petty_cash_request', 'label' => 'Petty Cash Request'],
        ['subject_type' => 'advance_request', 'label' => 'Advance Request'],
        ['subject_type' => 'cash_request', 'label' => 'Cash Request'],
        ['subject_type' => 'cash_clearance', 'label' => 'Cash Clearance'],
        ['subject_type' => 'advance_liquidation', 'label' => 'Advance Liquidation'],
    ];

    public function run(): void
    {
        $entity = Entity::query()->first();

        if ($entity === null) {
            return;
        }

        $admin = User::query()->where('email', 'admin@procurement.local')->first();

        foreach (self::SUBJECTS as $subject) {
            ApprovalSetting::query()->firstOrCreate(
                ['entity_id' => $entity->getKey(), 'subject_type' => $subject['subject_type']],
                [
                    'name' => "{$subject['label']} Approval",
                    'is_active' => true,
                    'created_by' => $admin?->getKey(),
                    'updated_by' => $admin?->getKey(),
                ],
            );

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
}

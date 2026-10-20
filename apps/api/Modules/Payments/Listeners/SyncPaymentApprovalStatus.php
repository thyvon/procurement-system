<?php

namespace Modules\Payments\Listeners;

use Modules\Approvals\Events\ApprovalStatusChanged;
use Modules\Payments\Models\AdvanceLiquidation;
use Modules\Payments\Models\CashClearance;
use Modules\Payments\Models\CashRequest;

/**
 * Translates approval-engine status changes into the payment documents' own
 * status vocabulary. The engine never writes subject tables — this listener
 * is the single bridge, running synchronously inside the approval transaction.
 * All five subject types live here in one listener because petty cash,
 * advance and cash requests share the CashRequest model, so a per-type copy
 * would only duplicate the same mapping.
 */
class SyncPaymentApprovalStatus
{
    public function handle(ApprovalStatusChanged $event): void
    {
        $model = match ($event->subjectType) {
            'petty_cash_request', 'advance_request', 'cash_request' => CashRequest::class,
            'cash_clearance' => CashClearance::class,
            'advance_liquidation' => AdvanceLiquidation::class,
            default => null,
        };

        if ($model === null) {
            return;
        }

        $status = match ($event->status) {
            'pending' => 'in_review',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'returned' => 'draft',
            'cancelled' => 'draft',
            default => null,
        };

        if ($status === null) {
            return;
        }

        $model::query()
            ->whereKey($event->subjectId)
            ->update([
                'status' => $status,
                'updated_by' => $event->actedBy,
            ]);

        // An approved clearance settles its source request: the money it
        // covered is now spent, so the request reaches `cleared`.
        if ($event->subjectType === 'cash_clearance' && $status === 'approved') {
            $clearance = CashClearance::query()->find($event->subjectId);

            if ($clearance !== null) {
                CashRequest::query()
                    ->whereKey($clearance->cash_request_id)
                    ->update([
                        'status' => CashRequest::STATUS_CLEARED,
                        'updated_by' => $event->actedBy,
                    ]);
            }
        }
    }
}

<?php

namespace Modules\PurchaseOrders\Listeners;

use Modules\Approvals\Events\ApprovalStatusChanged;
use Modules\PurchaseOrders\Models\Evaluation;

/**
 * Translates approval-engine status changes into the evaluation's own status
 * vocabulary. The engine never writes subject tables — this listener is the
 * single bridge, running synchronously inside the approval transaction.
 */
class SyncEvaluationApprovalStatus
{
    public function handle(ApprovalStatusChanged $event): void
    {
        if ($event->subjectType !== 'evaluation') {
            return;
        }

        $status = match ($event->status) {
            'pending' => 'in_review',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'returned' => 'draft',
            default => null,
        };

        if ($status === null) {
            return;
        }

        Evaluation::query()
            ->whereKey($event->subjectId)
            ->update([
                'status' => $status,
                'updated_by' => $event->actedBy,
            ]);
    }
}

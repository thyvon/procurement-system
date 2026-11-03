<?php

namespace Modules\Approvals\Events;

/**
 * Dispatched (synchronously, inside the approval transaction) whenever a
 * user records a decision — approve/reject/return or cancel — by either
 * transport (HTTP endpoint or Telegram inline buttons). Listeners that
 * must observe committed state (e.g. editing already-delivered Telegram
 * alerts) should dispatch after-commit jobs from the handler.
 */
class ApprovalActed
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $action,
        public readonly int $actorId,
        public readonly ?string $comment = null,
        public readonly ?string $stepLabel = null,
    ) {}
}

<?php

namespace Modules\Approvals\Events;

/**
 * Dispatched (synchronously, inside the approval transaction) whenever a
 * request's status changes. Subject modules listen and translate it into
 * their own status vocabulary — the engine never writes subject tables.
 */
class ApprovalStatusChanged
{
    public function __construct(
        public readonly string $subjectType,
        public readonly string $subjectId,
        public readonly string $status,
        public readonly string $requestId,
        public readonly ?int $actedBy = null,
    ) {}
}

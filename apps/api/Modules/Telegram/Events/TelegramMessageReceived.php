<?php

namespace Modules\Telegram\Events;

use Modules\Telegram\Models\TelegramMessage;

/**
 * Dispatched after an inbound supplier message has been deduplicated,
 * matched to a contact and persisted. The seam future modules subscribe to
 * for reply parsing, RFQ/PO confirmation and buyer notifications — Phase 1
 * has no consumers yet.
 */
class TelegramMessageReceived
{
    public function __construct(
        public readonly TelegramMessage $message,
    ) {}
}

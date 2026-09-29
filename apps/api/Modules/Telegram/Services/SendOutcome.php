<?php

namespace Modules\Telegram\Services;

/**
 * Result of one delivery attempt against the TDLib service. Returned instead
 * of thrown exceptions so the job can decide between release/fail/retry
 * without parsing transport errors.
 */
final readonly class SendOutcome
{
    private function __construct(
        public string $status,
        public ?int $retryAfter = null,
        public ?string $error = null,
    ) {}

    public static function sent(): self
    {
        return new self('sent');
    }

    public static function throttled(int $retryAfter): self
    {
        return new self('throttled', retryAfter: max(1, $retryAfter));
    }

    public static function failed(string $error): self
    {
        return new self('failed', error: $error);
    }
}

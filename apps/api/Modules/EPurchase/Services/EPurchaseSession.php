<?php

namespace Modules\EPurchase\Services;

/**
 * Company-side session captured at login (JWT + form token + cookies).
 * Never returned to the client; only used by the items/suppliers proxy.
 */
final readonly class EPurchaseSession
{
    public function __construct(
        public string $jwt,
        public ?string $formToken,
        public string $cookieHeader,
        public int $expiresAt,
    ) {}

    public function isExpired(): bool
    {
        return $this->expiresAt <= time();
    }
}

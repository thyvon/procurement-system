<?php

namespace Modules\EPurchase\Services;

final readonly class EPurchaseLoginResult
{
    public function __construct(
        public string $jwt,
        public string $name,
        public string $email,
        public ?string $userPhoto = null,
        public ?string $formToken = null,
        public ?string $position = null,
        /** @var array<int, string> Cookie name=value pairs from the login Set-Cookie headers. */
        public array $cookies = [],
    ) {}
}

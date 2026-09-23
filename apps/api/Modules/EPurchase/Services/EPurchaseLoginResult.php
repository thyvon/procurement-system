<?php

namespace Modules\EPurchase\Services;

final readonly class EPurchaseLoginResult
{
    public function __construct(
        public string $jwt,
        public string $name,
        public string $email,
        public ?string $userPhoto = null,
    ) {}
}

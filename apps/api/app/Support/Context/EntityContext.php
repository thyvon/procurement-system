<?php

namespace App\Support\Context;

final class EntityContext
{
    public function __construct(
        private readonly ?string $entityId = null,
    ) {}

    public function entityId(): ?string
    {
        return $this->entityId;
    }

    public function hasEntity(): bool
    {
        return $this->entityId !== null;
    }
}

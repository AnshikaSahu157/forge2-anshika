<?php

namespace App\Services;

class TenantContext
{
    protected ?int $organizationId = null;

    public function setOrganizationId(?int $id): void
    {
        $this->organizationId = $id;
    }

    public function getOrganizationId(): ?int
    {
        return $this->organizationId;
    }

    public function hasOrganizationId(): bool
    {
        return $this->organizationId !== null;
    }

    public function clear(): void
    {
        $this->organizationId = null;
    }
}

<?php

namespace App\Tenancy;

use App\Models\Organization;

/**
 * Sostiene el tenant (organización) activo durante el ciclo de vida de la
 * request. Se registra como singleton en el contenedor; el middleware
 * IdentifyTenant lo puebla y los modelos con BelongsToTenant lo consultan.
 */
class TenantManager
{
    protected ?Organization $tenant = null;

    public function set(Organization $organization): void
    {
        $this->tenant = $organization;
    }

    public function current(): ?Organization
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function isDemo(): bool
    {
        return (bool) $this->tenant?->is_demo;
    }

    public function forget(): void
    {
        $this->tenant = null;
    }
}

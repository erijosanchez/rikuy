<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Tenancy\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aísla un modelo por tenant. Mientras haya un tenant activo (TenantManager):
 *  - toda consulta se filtra por organization_id (global scope), y
 *  - todo registro nuevo hereda el organization_id del tenant activo.
 *
 * Si no hay tenant activo (p.ej. en consola/seeders) el scope no filtra; en
 * ese caso el organization_id debe asignarse explícitamente.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenant = app(TenantManager::class);

            if ($tenant->check()) {
                $builder->where(
                    $builder->getModel()->getTable().'.organization_id',
                    $tenant->id()
                );
            }
        });

        static::creating(function ($model) {
            $tenant = app(TenantManager::class);

            if ($tenant->check() && empty($model->organization_id)) {
                $model->organization_id = $tenant->id();
            }
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Escapa del aislamiento por tenant (usar con cuidado, solo fuera de request). */
    public function scopeWithoutTenantScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}

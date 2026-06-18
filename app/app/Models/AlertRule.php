<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Regla de alerta de un tenant: "la medida <measure> <direction> al menos
 * <threshold_pct>% respecto al mes anterior". El AlertEvaluator la contrasta
 * contra la serie mensual y registra un AlertEvent por cada periodo que rompe.
 */
class AlertRule extends Model
{
    use BelongsToTenant;

    public const MEASURES = ['monto', 'ordenes'];

    public const DIRECTIONS = ['drop', 'rise'];

    protected $fillable = [
        'organization_id',
        'name',
        'measure',
        'direction',
        'threshold_pct',
        'enabled',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'threshold_pct' => 'float',
            'enabled' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(AlertEvent::class);
    }

    /** Etiqueta legible de la medida observada. */
    public function measureLabel(): string
    {
        return $this->measure === 'ordenes' ? 'Órdenes' : 'Ventas';
    }

    /** Etiqueta legible de la dirección. */
    public function directionLabel(): string
    {
        return $this->direction === 'rise' ? 'suben' : 'caen';
    }
}

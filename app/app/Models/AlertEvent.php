<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Disparo registrado de una regla: el periodo cuya variación rompió el umbral,
 * con los valores observado/previo. Único por (regla, periodo) → la evaluación
 * es idempotente y no duplica alertas al reejecutarse.
 */
class AlertEvent extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'organization_id',
        'alert_rule_id',
        'period',
        'measure',
        'observed',
        'previous',
        'change_pct',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'observed' => 'float',
            'previous' => 'float',
            'change_pct' => 'float',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AlertRule::class, 'alert_rule_id');
    }
}

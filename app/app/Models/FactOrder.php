<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FactOrder extends Model
{
    use BelongsToTenant;

    protected $table = 'fact_orders';

    public $timestamps = false;

    protected $fillable = [
        'organization_id', 'dataset_id',
        'date_id', 'product_id', 'supplier_id', 'entity_id', 'region_id',
        'monto', 'cantidad',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'cantidad' => 'decimal:2',
        ];
    }

    public function date(): BelongsTo
    {
        return $this->belongsTo(DimDate::class, 'date_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DimProduct::class, 'product_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(DimSupplier::class, 'supplier_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(DimEntity::class, 'entity_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(DimRegion::class, 'region_id');
    }
}

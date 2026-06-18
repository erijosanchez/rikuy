<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DimSupplier extends Model
{
    use BelongsToTenant;

    protected $table = 'dim_supplier';

    protected $fillable = ['organization_id', 'name'];
}

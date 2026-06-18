<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DimProduct extends Model
{
    use BelongsToTenant;

    protected $table = 'dim_product';

    protected $fillable = ['organization_id', 'name'];
}

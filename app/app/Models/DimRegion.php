<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DimRegion extends Model
{
    use BelongsToTenant;

    protected $table = 'dim_region';

    protected $fillable = ['organization_id', 'name'];
}

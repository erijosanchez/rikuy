<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class DimEntity extends Model
{
    use BelongsToTenant;

    protected $table = 'dim_entity';

    protected $fillable = ['organization_id', 'name'];
}

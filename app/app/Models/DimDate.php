<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dimensión de fecha conformada (global). Sin tenant ni timestamps.
 */
class DimDate extends Model
{
    protected $table = 'dim_date';

    public $timestamps = false;

    protected $fillable = [
        'date', 'year', 'quarter', 'month', 'month_name', 'day',
    ];
}

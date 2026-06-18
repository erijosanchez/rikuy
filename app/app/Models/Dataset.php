<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'status',
        'rows',
    ];

    protected function casts(): array
    {
        return [
            'rows' => 'integer',
        ];
    }
}

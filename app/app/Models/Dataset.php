<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DatasetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dataset extends Model
{
    /** @use HasFactory<DatasetFactory> */
    use BelongsToTenant, HasFactory;

    public const STATUS_MAPPING = 'mapping';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'organization_id',
        'name',
        'source',
        'original_filename',
        'file_path',
        'column_map',
        'status',
        'error',
        'rows',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'rows' => 'integer',
            'processed_at' => 'datetime',
        ];
    }

    public function datasetRows(): HasMany
    {
        return $this->hasMany(DatasetRow::class);
    }
}

<?php

namespace App\Models;

use App\Enums\ImportStatusEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'original_filename',
        'source_path',
        'report_path',
        'status',
        'successful_rows',
        'failed_rows',
        'total_rows',
        'started_at',
        'finished_at',
        'source_path',
        'report_path',
    ];

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class, 'import_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected function casts(): array
    {
        return [
            'status' => ImportStatusEnum::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

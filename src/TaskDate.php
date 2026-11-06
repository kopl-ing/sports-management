<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class TaskDate extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $table = 'sm_task_dates';

    protected $fillable = [
        'task_id',
        'date',
        'time',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}

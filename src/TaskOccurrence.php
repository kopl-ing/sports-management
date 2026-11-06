<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;

class TaskOccurrence extends Model
{
    use HasUuids;

    protected $table = 'sm_task_occurrences';

    protected $fillable = [
        'task_id',
        'match_id',
        'date',
        'time',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'immutable_date',
            'cancelled_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(TeamMatch::class, 'match_id');
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->time === null ? $this->date : $this->date->setTimeFromTimeString($this->time);
    }
}

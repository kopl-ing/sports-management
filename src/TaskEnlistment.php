<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class TaskEnlistment extends Model
{
    use HasUuids;

    protected $table = 'sm_task_enlistments';

    protected $fillable = [
        'task_id',
        'occurrence_id',
        'team_member_id',
        'note',
        'ended_on',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'ended_on' => 'immutable_date',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(TaskOccurrence::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'team_member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function isWholeTask(): bool
    {
        return $this->occurrence_id === null;
    }

    public function startsOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->created_at)->startOfDay();
    }

    public function covers(TaskOccurrence $occurrence): bool
    {
        if (! $this->isWholeTask()) {
            return $this->occurrence_id === $occurrence->id;
        }

        return $occurrence->date->gte($this->startsOn())
            && ($this->ended_on === null || $occurrence->date->lte($this->ended_on));
    }

    public function isActive(): bool
    {
        return $this->ended_on === null || $this->ended_on->gte(CarbonImmutable::today());
    }
}

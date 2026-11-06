<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function outcomes(): HasMany
    {
        return $this->hasMany(TaskEnlistmentOutcome::class, 'enlistment_id');
    }

    /**
     * For a passed date: null means staff still have to confirm it. Needs `task.team` and `outcomes` loaded.
     */
    public function outcomeFor(TaskOccurrence $occurrence): ?TaskOutcome
    {
        $task = $this->task;

        if (! $task->team->tasks_approve_completion) {
            return TaskOutcome::Done;
        }

        return $this->outcomes->firstWhere('occurrence_id', $occurrence->id)?->outcome
            ?? ($this->isWholeTask() && ! $task->confirm_each_occurrence ? TaskOutcome::Done : null);
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

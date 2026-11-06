<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function enlistments(): HasMany
    {
        return $this->hasMany(TaskEnlistment::class, 'occurrence_id')->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TaskEnlistmentEvent::class, 'occurrence_id')->latest();
    }

    public function startsAt(): CarbonImmutable
    {
        return $this->time === null ? $this->date : $this->date->setTimeFromTimeString($this->time);
    }

    public function matchLabel(): ?string
    {
        $match = $this->match;

        return $match === null ? null : __('kopling-sports-management::messages.tasks.match.'.$match->home_away->value, ['opponent' => $match->opponent_name]);
    }

    public function hasPassed(): bool
    {
        return $this->time === null ? $this->date->lt(CarbonImmutable::today()) : $this->startsAt()->lt(now());
    }

    public function state(int $enlisted): TaskOccurrenceState
    {
        $task = $this->task;

        return match (true) {
            $this->cancelled_at !== null => TaskOccurrenceState::Cancelled,
            $this->hasPassed() => TaskOccurrenceState::Done,
            $task->max_people !== null && $enlisted >= $task->max_people => TaskOccurrenceState::Full,
            $task->min_people !== null && $enlisted < $task->min_people => TaskOccurrenceState::NeedsPeople,
            $task->min_people !== null => TaskOccurrenceState::Enough,
            default => TaskOccurrenceState::Open,
        };
    }
}

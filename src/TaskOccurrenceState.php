<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum TaskOccurrenceState: string
{
    case NeedsPeople = 'needs_people';
    case Enough = 'enough';
    case Full = 'full';
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('kopling-sports-management::messages.tasks.state.'.$this->value);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::NeedsPeople => 'badge-warning',
            self::Enough => 'badge-success',
            self::Full => 'badge-success',
            self::Open => 'badge-ghost',
            self::Done, self::Cancelled => 'badge-ghost opacity-60',
        };
    }

    public static function fromCount(Task $task, int $enlisted): self
    {
        return match (true) {
            $task->max_people !== null && $enlisted >= $task->max_people => self::Full,
            $task->min_people !== null && $enlisted < $task->min_people => self::NeedsPeople,
            $task->min_people !== null => self::Enough,
            default => self::Open,
        };
    }

    public function acceptsEnlisting(): bool
    {
        return in_array($this, [self::NeedsPeople, self::Enough, self::Open], true);
    }
}

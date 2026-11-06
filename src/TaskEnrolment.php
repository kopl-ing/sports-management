<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum TaskEnrolment: string
{
    case PerOccurrence = 'per_occurrence';
    case WholeTask = 'whole_task';

    public function label(): string
    {
        return __('kopling-sports-management::messages.tasks.enrolment.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $enrolment) => [$enrolment->value => $enrolment->label()])->all();
    }
}

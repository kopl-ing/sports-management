<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum StaffRole: string
{
    case Coach = 'coach';
    case Referee = 'referee';

    public function label(): string
    {
        return __('kopling-sports-management::messages.staff_role.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $role) => [$role->value => $role->label()])->all();
    }
}

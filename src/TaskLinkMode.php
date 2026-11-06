<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

enum TaskLinkMode: string
{
    case Team = 'team';
    case PerPlayer = 'per_player';

    public function label(): string
    {
        return __('kopling-sports-management::messages.tasks.link_mode.'.$this->value);
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $mode) => [$mode->value => $mode->label()])->all();
    }
}

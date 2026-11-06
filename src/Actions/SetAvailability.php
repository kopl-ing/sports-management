<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Kopling\SportsManagement\AvailabilityStatus;
use Kopling\SportsManagement\TeamMatch;

/**
 * Availability per player for a match; `$replace` clears everyone not given (the web form sends the full state).
 */
class SetAvailability
{
    /**
     * @param array<string, string|null> $statuses team member id => status, null clears it
     */
    public static function handle(TeamMatch $match, array $statuses, bool $replace): void
    {
        $statuses = Validator::validate(['availability' => $statuses], [
            'availability' => ['array'],
            'availability.*' => ['nullable', Rule::enum(AvailabilityStatus::class)],
        ])['availability'] ?? [];

        foreach ($match->team->members()->onTeamAt($match->scheduled_at)->pluck('id') as $memberId) {
            if (! $replace && ! array_key_exists($memberId, $statuses)) {
                continue;
            }

            $status = $statuses[$memberId] ?? null;
            $status === null
                ? $match->availabilities()->where('team_member_id', $memberId)->delete()
                : $match->availabilities()->updateOrCreate(['team_member_id' => $memberId], ['status' => $status]);
        }
    }
}

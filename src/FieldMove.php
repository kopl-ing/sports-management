<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Validation\Rule;

class FieldMove
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(Team $team, TeamMatch $match): array
    {
        $member = Rule::exists('sm_team_members', 'id')->where('team_id', $team->id);

        return [
            'team_member_id' => ['required', 'uuid', $member, Rule::notIn($match->absentMemberIds())],
            'zone' => ['nullable', Rule::enum(Position::class)],
            'replace_team_member_id' => ['nullable', 'uuid', $member],
        ];
    }

    /**
     * @param array{team_member_id: string, zone?: string|null, replace_team_member_id?: string|null} $data
     * @param array<string, Position|null> $placement
     * @return array<string, Position|null>
     */
    public static function fromRequest(array $data, array $placement): array
    {
        return self::changes(
            $placement,
            $data['team_member_id'],
            isset($data['zone']) ? Position::from($data['zone']) : null,
            $data['replace_team_member_id'] ?? null,
        );
    }

    /**
     * Dropping onto a field player takes over their zone; they swap to the mover's zone, or the bench.
     *
     * @param array<string, Position|null> $placement zone per team member id currently on the field
     * @return array<string, Position|null> new zone (null = bench) per team member id, benched first
     */
    public static function changes(array $placement, string $memberId, ?Position $zone, ?string $replaceId): array
    {
        $changes = $replaceId !== null && $replaceId !== $memberId && array_key_exists($replaceId, $placement)
            ? [$replaceId => $placement[$memberId] ?? null, $memberId => $placement[$replaceId]]
            : [$memberId => $zone];

        $changes = array_filter(
            $changes,
            fn (?Position $to, string $id) => array_key_exists($id, $placement) ? $placement[$id] !== $to || $to === null : $to !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        uasort($changes, fn (?Position $a, ?Position $b) => ($a !== null) <=> ($b !== null));

        return $changes;
    }

    /**
     * @param array<string, Position|null> $placement
     * @param array<string, Position|null> $changes
     */
    public static function limitError(array $placement, array $changes, ?int $maxOnField): ?string
    {
        foreach ($changes as $memberId => $zone) {
            $placement[$memberId] = $zone;
        }
        $placement = array_filter($placement, fn (?Position $zone) => $zone !== null);

        return match (true) {
            count(array_filter($placement, fn (Position $zone) => $zone === Position::Keeper)) > 1 => __('kopling-sports-management::messages.one_keeper'),
            $maxOnField !== null && count($placement) > $maxOnField => __('kopling-sports-management::messages.too_many_players', ['max' => $maxOnField]),
            default => null,
        };
    }
}

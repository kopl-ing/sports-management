<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

class FieldSlots
{
    /**
     * @param array<int, string> $ids team member ids in fallback order
     * @param array<string, float> $slots
     * @return array<int, string> slotted players by slot, then the rest in fallback order
     */
    public static function order(array $ids, array $slots): array
    {
        $fallback = array_flip($ids);
        usort($ids, fn (string $a, string $b) => [$slots[$a] ?? INF, $fallback[$a]] <=> [$slots[$b] ?? INF, $fallback[$b]]);

        return $ids;
    }

    /**
     * Slots only count while the player is still in the zone they were set for.
     *
     * @param array<string, Position|null> $placement zone per team member id
     * @param array<string, array{0: Position, 1: float}> $rows stored zone and slot per team member id
     * @return array<string, float>
     */
    public static function current(array $placement, array $rows): array
    {
        $slots = [];
        foreach ($rows as $memberId => [$zone, $slot]) {
            if (($placement[$memberId] ?? null) === $zone) {
                $slots[$memberId] = $slot;
            }
        }

        return $slots;
    }

    /**
     * A swap trades places; any other move to a zone lands before `$beforeId`, or last.
     *
     * @param array<string, Position|null> $placement zone per team member id, before the move
     * @param array<int, string> $order every team member id in fallback order
     * @param array<string, array{0: Position, 1: float}> $rows
     * @return array<string, array{0: Position, 1: float}> slot rows to write
     */
    public static function writes(array $placement, array $order, array $rows, string $memberId, ?Position $zone, ?string $beforeId, ?string $replaceId): array
    {
        $writes = [];
        $slots = self::current($placement, $rows);

        $row = function (Position $zone) use ($placement, $order, &$slots, &$writes): array {
            $ids = self::order(array_values(array_filter($order, fn (string $id) => ($placement[$id] ?? null) === $zone)), $slots);
            if (array_diff($ids, array_keys($slots)) !== []) {
                foreach ($ids as $index => $id) {
                    $slots[$id] = $index + 1.0;
                    $writes[$id] = [$zone, $index + 1.0];
                }
            }

            return $ids;
        };

        if ($replaceId !== null && $replaceId !== $memberId && isset($placement[$replaceId])) {
            $theirZone = $placement[$replaceId];
            $ownZone = $placement[$memberId] ?? null;
            $row($theirZone);
            if ($ownZone !== null) {
                $row($ownZone);
                $writes[$replaceId] = [$ownZone, $slots[$memberId]];
            }
            $writes[$memberId] = [$theirZone, $slots[$replaceId]];

            return $writes;
        }

        if ($zone === null) {
            return $writes;
        }

        $ids = array_values(array_diff($row($zone), [$memberId]));
        $at = $beforeId === null ? false : array_search($beforeId, $ids, true);
        $at = $at === false ? count($ids) : $at;
        $previous = $at > 0 ? $slots[$ids[$at - 1]] : null;
        $next = $at < count($ids) ? $slots[$ids[$at]] : null;

        $writes[$memberId] = [$zone, match (true) {
            $previous === null && $next === null => 1.0,
            $previous === null => $next - 1,
            $next === null => $previous + 1,
            default => ($previous + $next) / 2,
        }];

        return $writes;
    }
}

<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Actions;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\AvailabilityStatus;
use Kopling\SportsManagement\HomeAway;
use Kopling\SportsManagement\RefereeDuty;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamFormatPreset;
use Kopling\SportsManagement\TeamMatch;

/**
 * Plans or changes a match from form-shaped input, for the match form and the MCP tools alike.
 */
class SaveMatch
{
    /**
     * @param array<string, mixed> $input
     */
    public static function handle(Team $team, array $input, ?TeamMatch $match = null): TeamMatch
    {
        $data = self::validated($team, $input);

        if (isset($data['players_on_field'])) {
            $presetId = array_key_exists('format_preset_id', $data) ? $data['format_preset_id'] : $match?->format_preset_id;
            $preset = $presetId === null ? $team->formatPreset : TeamFormatPreset::find($presetId);
            if ((int) $data['players_on_field'] === $preset?->players_on_field) {
                $data['players_on_field'] = null;
            }
        }

        if ($match !== null) {
            if (array_key_exists('players_on_field', $data) && $data['players_on_field'] != $match->players_on_field && $match->periods()->exists()) {
                throw ValidationException::withMessages(['players_on_field' => __('kopling-sports-management::messages.players_on_field_started')]);
            }

            $match->update($data);

            return $match;
        }

        $match = $team->matches()->create($data + ['public' => $team->public_matches]);
        $match->availabilities()->createMany(
            $team->members()->where('guest', true)->pluck('id')
                ->map(fn (string $memberId) => ['team_member_id' => $memberId, 'status' => AvailabilityStatus::Absent]),
        );

        return $match;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private static function validated(Team $team, array $input): array
    {
        $data = Validator::validate($input, [
            'opponent_name' => ['required', 'string', 'max:255'],
            'home_away' => ['required', Rule::enum(HomeAway::class)],
            'location_address' => ['nullable', 'string', 'max:1000'],
            'format_preset_id' => ['nullable', 'uuid', Rule::exists('sm_team_format_presets', 'id')->where('sport', $team->sport->value)],
            'play_minutes' => ['nullable', 'integer', 'min:1', 'max:240'],
            'players_on_field' => ['nullable', 'integer', 'min:1', 'max:30'],
            // Stored as local wall-clock time, so an offset would be silently dropped.
            'scheduled_at' => ['required', 'date_format:Y-m-d\TH:i,Y-m-d H:i,Y-m-d\TH:i:s,Y-m-d H:i:s'],
            'referee_person_id' => ['nullable', 'uuid', Rule::in($team->referees()->pluck('people.id')->all())],
            'referee_duties' => ['nullable', 'array', 'required_with:referee_person_id'],
            'referee_duties.*' => [Rule::in(array_map(fn (RefereeDuty $duty) => $duty->value, RefereeDuty::for($team->sport)))],
            'public' => ['sometimes', 'boolean'],
        ]);

        $data['referee_person_id'] ??= null;
        $data['referee_duties'] = $data['referee_person_id'] === null ? null : array_values(array_unique($data['referee_duties']));

        return $data;
    }
}

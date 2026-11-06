<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Kopling\Core\People\Person;
use Kopling\SportsManagement\Controllers\FieldNames;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

/**
 * Adds or changes a roster member from form-shaped input; a `left_on` takes them off the team from that date (see `TeamMember::leaveOn()`).
 */
class SaveTeamMember
{
    /**
     * @param array<string, mixed> $input
     */
    public static function handle(Team $team, array $input, Person $by, ?TeamMember $member = null): TeamMember
    {
        $data = Validator::validate($input, [
            'name' => ['required', 'string', 'max:255'],
            'jersey_number' => ['nullable', 'string', 'max:16'],
            'positions' => ['sometimes', 'array'],
            'positions.*' => [Rule::enum(Position::class)->only($team->sport->config()->zones())],
            'guest' => ['sometimes', 'boolean'],
            'left_on' => $member === null ? ['exclude'] : ['nullable', 'date_format:Y-m-d'],
        ], [], FieldNames::all());

        return DB::transaction(function () use ($team, $data, $by, $member) {
            $attributes = [
                'jersey_number' => $data['jersey_number'] ?? null,
                'positions' => $data['positions'] ?? [],
                'guest' => $data['guest'] ?? false,
            ];

            if ($member === null) {
                return TeamMember::create($attributes + [
                    'team_id' => $team->id,
                    'person_id' => Person::create(['name' => $data['name']])->id,
                ]);
            }

            $member->person->update(['name' => $data['name']]);
            $member->update($attributes);

            $leftOn = isset($data['left_on']) ? CarbonImmutable::parse($data['left_on']) : null;
            if ($leftOn === null) {
                $member->update(['left_on' => null]);
            } elseif ($member->left_on === null || ! $leftOn->equalTo($member->left_on)) {
                $member->leaveOn($leftOn, $by);
            }

            return $member;
        });
    }
}

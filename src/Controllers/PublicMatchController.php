<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Http\Response;
use Kopling\SportsManagement\FieldSlots;
use Kopling\SportsManagement\MatchGoal;
use Kopling\SportsManagement\MatchLineup;
use Kopling\SportsManagement\MatchState;
use Kopling\SportsManagement\PeriodType;
use Kopling\SportsManagement\Position;
use Kopling\SportsManagement\TeamMatch;
use Kopling\SportsManagement\TeamMember;

/**
 * Guests see initials only: the view gets plain arrays, never a Person or TeamMember.
 */
class PublicMatchController
{
    public const POLL_SECONDS = 15;

    public function __invoke(string $token): Response
    {
        $match = TeamMatch::findPublic($token) ?? abort(404);
        $team = $match->team->load(['formatPreset', 'members.person']);
        $match->setRelation('team', $team)->load(['formatPreset', 'lineup', 'slots', 'periods', 'substitutions', 'goals', 'sanctions']);

        $timeline = $match->timeline();
        $state = $timeline->state();
        $initials = TeamMember::shortInitials($team->members);
        $players = $team->members->mapWithKeys(fn (TeamMember $member) => [$member->id => [
            'initials' => $initials[$member->id],
            'color' => $member->person->avatarColor(),
        ]]);

        $placement = match ($state) {
            MatchState::Planned => $match->lineup->mapWithKeys(fn (MatchLineup $slot) => [$slot->team_member_id => $slot->zone])->all(),
            MatchState::Live => array_map(fn (?Position $zone) => $zone ?? $match->sportConfig()->defaultZone(), $timeline->onField()),
            MatchState::Ended => [],
        };
        $slots = FieldSlots::current($placement, $match->slotRows());
        $field = collect($match->sportConfig()->zones($match->effectiveFormatPreset()))
            ->map(fn (Position $zone) => [
                'zone' => $zone,
                'players' => array_map(
                    fn (string $memberId) => $players[$memberId],
                    FieldSlots::order(array_keys(array_filter($placement, fn (?Position $placed) => $placed === $zone)), $slots),
                ),
            ])
            ->all();

        $periods = $timeline->periods()->keyBy('id');
        $goals = $match->goals
            ->sortBy([
                fn (MatchGoal $a, MatchGoal $b) => $periods[$b->period_id]->sequence <=> $periods[$a->period_id]->sequence,
                ['offset_seconds', 'desc'],
                ['created_at', 'desc'],
            ])
            ->map(fn (MatchGoal $goal) => [
                'minute' => intdiv($timeline->matchSecond($periods[$goal->period_id], $goal->offset_seconds), 60),
                'opponent' => $goal->opponent,
                'own_goal' => $goal->own_goal,
                'points' => $goal->points ?? 1,
                'scorer' => $players[$goal->scorer_team_member_id] ?? null,
                'assist' => $players[$goal->assist_team_member_id] ?? null,
            ])
            ->values();

        $running = $timeline->runningPeriod();

        return response()
            ->view('kopling-sports-management::matches.public', [
                'teamName' => $team->name,
                'opponent' => $match->opponent_name,
                'homeAway' => $match->home_away,
                'scheduledAt' => $match->scheduled_at,
                'location' => $match->location_address,
                'sport' => $team->sport,
                'url' => $match->publicUrl(),
                'state' => $state,
                'score' => $timeline->score(),
                'running' => $running === null ? null : [
                    'label' => $timeline->label($running),
                    'seconds' => $timeline->length($running),
                    'ticking' => $running->type === PeriodType::Play,
                ],
                'field' => $state === MatchState::Ended || $placement === [] ? null : $field,
                'goals' => $goals,
                'multiplePoints' => count($match->sportConfig()->pointValues()) > 1,
                'pollSeconds' => $state === MatchState::Ended ? null : self::POLL_SECONDS,
            ])
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}

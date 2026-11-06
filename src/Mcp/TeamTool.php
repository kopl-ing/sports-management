<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Mcp;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Kopling\Mcp\Tool;
use Kopling\OAuth\Person;
use Kopling\SportsManagement\Task;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMatch;
use Kopling\SportsManagement\TeamMember;
use Laravel\Mcp\Request;

/**
 * Base for sports-management tools about one team: resolves `team_id` through `TeamAccess` and records it in the audit log.
 */
abstract class TeamTool extends Tool
{
    public const ACCESS = 'kopling-sports-management::access-sports-management';

    /** The extension's own permission a tool needs on top of `ACCESS`, as on the matching web route. */
    protected ?string $permission = null;

    protected function allows(Person $person): bool
    {
        if (TeamAccess::isAdmin($person)) {
            return true;
        }

        return $person->hasPermission(self::ACCESS)
            && ($this->permission === null || $person->hasPermission($this->permission));
    }

    /**
     * @throws AuthorizationException when the team isn't reachable, or `$coachOnly` and the person doesn't coach it
     */
    protected function team(Request $request, bool $coachOnly = false): Team
    {
        $team = TeamAccess::find($this->person(), $request->get('team_id'))
            ?? throw new AuthorizationException('Team not found, or not available to you through an assistant.');

        if ($coachOnly && ! TeamAccess::coaches($this->person(), $team)) {
            throw new AuthorizationException('Only the team\'s coaches can see this.');
        }

        $this->aboutTeam($team->id);

        return $team;
    }

    protected function match(Request $request, Team $team): TeamMatch
    {
        return $team->matches()->whereKey((string) $request->get('match_id'))->first()
            ?? throw new AuthorizationException('Match not found.');
    }

    protected function task(Request $request, Team $team): Task
    {
        return $team->tasks()->whereKey((string) $request->get('task_id'))->first()?->setRelation('team', $team)
            ?? throw new AuthorizationException('Task not found.');
    }

    protected function occurrence(Task $task, mixed $id): ?TaskOccurrence
    {
        return $id === null ? null : ($task->occurrences()->whereKey((string) $id)->first() ?? throw new AuthorizationException('Date not found.'));
    }

    protected function enlistment(Request $request, Team $team): TaskEnlistment
    {
        $enlistment = TaskEnlistment::whereKey((string) $request->get('enlistment_id'))
            ->whereHas('task', fn ($query) => $query->where('team_id', $team->id))
            ->first() ?? throw new AuthorizationException('Sign-up not found.');
        $enlistment->task->setRelation('team', $team);

        return $enlistment;
    }

    protected function player(Request $request, Team $team, string $key = 'player_id'): TeamMember
    {
        return $team->members()->whereKey((string) $request->get($key))->with('person')->first()
            ?? throw new AuthorizationException('Player not found.');
    }

    protected function tasksTeam(Request $request): Team
    {
        $team = $this->team($request, coachOnly: true);
        if (! $team->tasks_enabled) {
            throw new AuthorizationException('Tasks are switched off for this team.');
        }

        return $team;
    }

    /**
     * @return array<string, mixed>
     */
    protected function teamSchema(JsonSchema $schema): array
    {
        return ['team_id' => $schema->string()->description('The team\'s id, from list-my-teams.')->required()];
    }

    /**
     * Names and numbers only: these are children's records.
     *
     * @return array<string, mixed>
     */
    protected static function playerData(TeamMember $member): array
    {
        return array_filter([
            'id' => $member->id,
            'name' => $member->person->name,
            'jersey_number' => $member->jersey_number,
            'positions' => $member->positions?->pluck('value')->all() ?: null,
            'guest' => $member->guest ?: null,
            'left_on' => $member->left_on?->toDateString(),
        ], fn ($value) => $value !== null);
    }
}

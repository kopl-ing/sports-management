<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Kopling\SportsManagement\Task;
use Kopling\SportsManagement\TaskBoard;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnlistments;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

/**
 * Same rule as the public match page: the view gets plain arrays with first name and last initial, never a Person.
 */
class PublicTasksController
{
    public function picker(Request $request, string $token): Response
    {
        $team = Team::findByTasksToken($token) ?? abort(404);
        $members = TeamMember::sorted($team->members()->where('guest', false)->with('person')->get());

        return $this->private(response()->view('kopling-sports-management::tasks.public-picker', [
            'teamName' => $team->name,
            'storageKey' => 'kopling-sm-tasks:'.$token,
            'pick' => $request->boolean('pick'),
            'players' => $members->map(fn (TeamMember $member) => $this->shown($member) + [
                'url' => route('kopling-sports-management::live/tasks.team.player', [$token, $member->id]),
            ])->all(),
        ]));
    }

    public function teamPlayer(Request $request, string $token, string $member): Response
    {
        [$team, $member] = $this->teamMember($token, $member);

        return $this->show($request, $team, $member, [
            'url' => route('kopling-sports-management::live/tasks.team.player', [$token, $member->id]),
            'pickUrl' => route('kopling-sports-management::live/tasks.team', [$token, 'pick' => 1]),
            'storageKey' => 'kopling-sm-tasks:'.$token,
            'enlistUrl' => route('kopling-sports-management::live/tasks.team.enlist', [$token, $member->id]),
            'leaveUrl' => fn (string $enlistment) => route('kopling-sports-management::live/tasks.team.leave', [$token, $member->id, $enlistment]),
        ]);
    }

    public function teamEnlist(Request $request, string $token, string $member): RedirectResponse
    {
        [$team, $member] = $this->teamMember($token, $member);

        return $this->enlist($request, $team, $member, route('kopling-sports-management::live/tasks.team.player', [$token, $member->id]));
    }

    public function teamLeave(Request $request, string $token, string $member, TaskEnlistment $enlistment): RedirectResponse
    {
        [, $member] = $this->teamMember($token, $member);

        return $this->leave($member, $enlistment, route('kopling-sports-management::live/tasks.team.player', [$token, $member->id]));
    }

    public function player(Request $request, string $token): Response
    {
        $member = TeamMember::findByTasksToken($token) ?? abort(404);

        return $this->show($request, $member->team, $member, [
            'url' => route('kopling-sports-management::live/tasks.player', $token),
            'pickUrl' => null,
            'storageKey' => null,
            'enlistUrl' => route('kopling-sports-management::live/tasks.player.enlist', $token),
            'leaveUrl' => fn (string $enlistment) => route('kopling-sports-management::live/tasks.player.leave', [$token, $enlistment]),
        ]);
    }

    public function playerEnlist(Request $request, string $token): RedirectResponse
    {
        $member = TeamMember::findByTasksToken($token) ?? abort(404);

        return $this->enlist($request, $member->team, $member, route('kopling-sports-management::live/tasks.player', $token));
    }

    public function playerLeave(string $token, TaskEnlistment $enlistment): RedirectResponse
    {
        $member = TeamMember::findByTasksToken($token) ?? abort(404);

        return $this->leave($member, $enlistment, route('kopling-sports-management::live/tasks.player', $token));
    }

    /**
     * @return array{0: Team, 1: TeamMember}
     */
    private function teamMember(string $token, string $memberId): array
    {
        $team = Team::findByTasksToken($token) ?? abort(404);
        $member = $team->members()->where('guest', false)->whereKey($memberId)->with('person')->first() ?? abort(404);

        return [$team, $member];
    }

    /**
     * @param array{url: string, pickUrl: ?string, storageKey: ?string, enlistUrl: string, leaveUrl: \Closure(string): string} $links
     */
    private function show(Request $request, Team $team, TeamMember $member, array $links): Response
    {
        $weeks = min(max($request->integer('weeks', TaskBoard::WEEKS), TaskBoard::WEEKS), 52);
        $board = new TaskBoard($team, $weeks);
        $mine = fn (TaskEnlistment $enlistment) => $enlistment->team_member_id === $member->id;
        $people = fn ($enlistments) => $enlistments->map(fn (TaskEnlistment $enlistment) => $this->shown($enlistment->member) + [
            'note' => $enlistment->note,
            'mine' => $mine($enlistment),
            'leave' => $mine($enlistment) && TaskEnlistments::canLeave($enlistment) ? $enlistment->id : null,
        ])->values()->all();

        $occurrences = $board->occurrences
            ->filter(fn (TaskOccurrence $occurrence) => $occurrence->cancelled_at === null || $occurrence->enlistments->contains($mine))
            ->map(function (TaskOccurrence $occurrence) use ($board, $people) {
                $state = $board->state($occurrence);

                $when = $occurrence->startsAt()->translatedFormat($occurrence->time === null ? 'l j F' : 'l j F, H:i');

                return [
                    'id' => $occurrence->id,
                    'when' => $when,
                    'details' => $this->details($occurrence->task, true),
                    'undo' => $this->undoRule($occurrence->task, false),
                    'task_id' => $occurrence->task_id,
                    'date' => $occurrence->date,
                    'time' => $occurrence->time === null ? null : substr($occurrence->time, 0, 5),
                    'title' => $occurrence->task->title,
                    'description' => $occurrence->task->description,
                    'state' => $state,
                    'count' => $board->count($occurrence),
                    'max' => $occurrence->task->max_people,
                    'people' => $people($occurrence->enlistments),
                    'can_enlist' => $state->acceptsEnlisting(),
                ];
            });

        return $this->private(response()->view('kopling-sports-management::tasks.public', $links + [
            'teamName' => $team->name,
            'player' => $this->shown($member),
            'wholeTasks' => $board->wholeTasks()->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'schedule' => $task->scheduleSummary(),
                'when' => $task->scheduleSummary(),
                'details' => $this->details($task, false),
                'undo' => $this->undoRule($task, true),
                'count' => $board->activeEnlistments($task)->count(),
                'max' => $task->max_people,
                'people' => $people($board->activeEnlistments($task)),
                'can_enlist' => $board->wholeTaskAcceptsEnlisting($task),
            ])->all(),
            'days' => $occurrences->groupBy(fn (array $row) => $row['date']->toDateString())->all(),
            'laterUrl' => $board->hasLater ? $links['url'].'?'.http_build_query(['weeks' => $weeks + TaskBoard::WEEKS]) : null,
        ]));
    }

    private function enlist(Request $request, Team $team, TeamMember $member, string $back): RedirectResponse
    {
        $data = $request->validate([
            'task_id' => ['required', 'uuid'],
            'occurrence_id' => ['nullable', 'uuid'],
            'note' => ['nullable', 'string', 'max:100'],
        ]);

        $task = $team->tasks()->whereKey($data['task_id'])->first() ?? abort(404);
        $occurrence = isset($data['occurrence_id']) ? ($task->occurrences()->whereKey($data['occurrence_id'])->first() ?? abort(404)) : null;

        try {
            TaskEnlistments::enlist($task->setRelation('team', $team), $occurrence, $member, $data['note'] ?? null);
        } catch (ValidationException $e) {
            return redirect()->to($back)->withErrors($e->errors());
        }

        return redirect()->to($back);
    }

    private function leave(TeamMember $member, TaskEnlistment $enlistment, string $back): RedirectResponse
    {
        abort_unless($enlistment->team_member_id === $member->id, 404);

        try {
            TaskEnlistments::leave($enlistment);
        } catch (ValidationException $e) {
            return redirect()->to($back)->withErrors($e->errors());
        }

        return redirect()->to($back);
    }

    private function details(Task $task, bool $withSchedule): string
    {
        return Str::ucfirst(collect([$withSchedule ? $task->scheduleSummary() : null, $task->durationSummary()])->filter()->join(' · '));
    }

    private function undoRule(Task $task, bool $wholeTask): string
    {
        return __('kopling-sports-management::messages.tasks.link.undo_rule.'.($wholeTask ? 'whole.' : '').$task->undo_window->value);
    }

    /**
     * @return array{name: string, color: ?string}
     */
    private function shown(TeamMember $member): array
    {
        return ['name' => $member->shortName(), 'color' => $member->person->avatarColor()];
    }

    private function private(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }
}

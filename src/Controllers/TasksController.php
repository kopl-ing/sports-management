<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Kopling\SportsManagement\Actions\SaveTask;
use Kopling\SportsManagement\Task;
use Kopling\SportsManagement\TaskBalance;
use Kopling\SportsManagement\TaskBoard;
use Kopling\SportsManagement\TaskEnlistment;
use Kopling\SportsManagement\TaskEnlistments;
use Kopling\SportsManagement\TaskOccurrence;
use Kopling\SportsManagement\TaskOutcome;
use Kopling\SportsManagement\TaskReview;
use Kopling\SportsManagement\Team;
use Kopling\SportsManagement\TeamMember;

class TasksController
{
    public function index(Request $request, Team $team): View
    {
        $this->authorizeCoach($request, $team);

        $weeks = min(max($request->integer('weeks', TaskBoard::WEEKS), TaskBoard::WEEKS), 52);
        $filter = in_array($request->query('filter'), ['open', 'past', 'confirm'], true) ? $request->query('filter') : null;
        if ($filter === 'confirm' && ! $team->tasks_approve_completion) {
            $filter = null;
        }

        if ($filter === 'past' || $filter === 'confirm') {
            $review = new TaskReview($team, $weeks, $filter === 'confirm');

            return view('kopling-sports-management::tasks.index', [
                'team' => $team,
                'filter' => $filter,
                'review' => $review,
                'players' => $this->players($team),
                'weeks' => $review->occurrences->groupBy(fn (TaskOccurrence $occurrence) => $occurrence->date->startOfWeek()->toDateString()),
                'laterUrl' => $review->hasEarlier
                    ? route('kopling-sports-management::sports-management/tasks.index', ['team' => $team, 'filter' => $filter, 'weeks' => $weeks + TaskBoard::WEEKS])
                    : null,
            ]);
        }

        $board = new TaskBoard($team, $weeks);
        $openOnly = $filter === 'open';
        $occurrences = $board->occurrences
            ->filter(fn (TaskOccurrence $occurrence) => ! $openOnly || $board->state($occurrence)->acceptsEnlisting())
            ->load(['events.member.person', 'events.creator']);

        return view('kopling-sports-management::tasks.index', [
            'team' => $team,
            'board' => $board,
            'wholeTasks' => $board->wholeTasks()->load(['events' => fn ($query) => $query->whereNull('occurrence_id')->with(['member.person', 'creator'])]),
            'weeks' => $occurrences->groupBy(fn (TaskOccurrence $occurrence) => $occurrence->date->startOfWeek()->toDateString()),
            'players' => $this->players($team),
            'filter' => $filter,
            'laterUrl' => $board->hasLater
                ? route('kopling-sports-management::sports-management/tasks.index', ['team' => $team, 'weeks' => $weeks + TaskBoard::WEEKS] + ($filter ? ['filter' => $filter] : []))
                : null,
        ]);
    }

    public function balance(Request $request, Team $team): View
    {
        $this->authorizeCoach($request, $team);

        return view('kopling-sports-management::tasks.balance', [
            'team' => $team,
            'rows' => TaskBalance::for($team),
        ]);
    }

    public function outcome(Request $request, Team $team, TaskEnlistment $enlistment): RedirectResponse
    {
        $this->authorizeTask($request, $team, $enlistment->task);
        abort_unless($team->tasks_approve_completion, 404);

        $data = $request->validate([
            'occurrence_id' => ['required', 'uuid'],
            'outcome' => ['required', Rule::enum(TaskOutcome::class)],
        ]);
        $occurrence = $enlistment->task->occurrences()->whereKey($data['occurrence_id'])->first() ?? abort(404);
        TaskEnlistments::recordOutcome($enlistment->setRelation('task', $enlistment->task->setRelation('team', $team)), $occurrence, TaskOutcome::from($data['outcome']), $request->user());

        return back();
    }

    public function confirm(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCoach($request, $team);
        abort_unless($team->tasks_approve_completion, 404);

        $data = $request->validate([
            'occurrence_ids' => ['required', 'array', 'max:500'],
            'occurrence_ids.*' => ['uuid'],
        ]);
        $review = new TaskReview($team, pendingOnly: true);
        $review->confirmAll($review->occurrences->whereIn('id', $data['occurrence_ids']), $request->user());

        return back();
    }

    public function enlist(Request $request, Team $team, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $team, $task);

        $data = $request->validate([
            'team_member_id' => ['required', 'uuid'],
            'occurrence_id' => ['nullable', 'uuid'],
            'note' => ['nullable', 'string', 'max:100'],
            'starts_on' => ['nullable', 'date_format:Y-m-d'],
        ], [], FieldNames::all());
        $member = $team->members()->onTeamAt(now())->where('guest', false)->whereKey($data['team_member_id'])->first() ?? abort(404);
        $occurrence = isset($data['occurrence_id']) ? ($task->occurrences()->whereKey($data['occurrence_id'])->first() ?? abort(404)) : null;

        TaskEnlistments::enlist(
            $task->setRelation('team', $team),
            $occurrence,
            $member,
            $data['note'] ?? null,
            $request->user(),
            isset($data['starts_on']) ? CarbonImmutable::parse($data['starts_on']) : null,
        );

        return back();
    }

    public function leave(Request $request, Team $team, TaskEnlistment $enlistment): RedirectResponse
    {
        $this->authorizeTask($request, $team, $enlistment->task);

        TaskEnlistments::leave($enlistment, $request->user());

        return back();
    }

    public function changeDate(Request $request, Team $team, TaskEnlistment $enlistment): RedirectResponse
    {
        $this->authorizeTask($request, $team, $enlistment->task);
        abort_unless($enlistment->isWholeTask(), 404);

        $data = $request->validate(['occurrence_id' => ['required', 'uuid'], 'action' => ['required', 'in:skip,restore']]);
        $occurrence = $enlistment->task->occurrences()->whereKey($data['occurrence_id'])->first() ?? abort(404);
        $enlistment->task->setRelation('team', $team);

        $data['action'] === 'skip'
            ? TaskEnlistments::skip($enlistment, $occurrence, $request->user())
            : TaskEnlistments::restore($enlistment, $occurrence, $request->user());

        return back();
    }

    public function regenerateLink(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCoach($request, $team);

        $team->regenerateTasksToken();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function regenerateMemberLink(Request $request, Team $team, TeamMember $teamMember): RedirectResponse
    {
        $this->authorizeCoach($request, $team);
        abort_unless($teamMember->team_id === $team->id, 404);

        $teamMember->regenerateTasksToken();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function store(Request $request, Team $team): RedirectResponse
    {
        $this->authorizeCoach($request, $team);

        SaveTask::handle($team, $request->all(), $request->user());

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function update(Request $request, Team $team, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $team, $task);

        SaveTask::handle($team, $request->all(), $request->user(), $task);

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function destroy(Request $request, Team $team, Task $task): RedirectResponse
    {
        $this->authorizeTask($request, $team, $task);

        $task->delete();

        return redirect()->route('kopling-sports-management::sports-management/teams.show', $team);
    }

    public function scheduleFields(Request $request, Team $team): View
    {
        $this->authorizeCoach($request, $team);

        $values = $request->query();
        if ($request->boolean('add_date')) {
            $values['dates'] = [...array_values((array) ($values['dates'] ?? [])), ['date' => '', 'time' => '']];
        }

        return view('kopling-sports-management::tasks.schedule-fields', ['team' => $team, 'values' => $values]);
    }

    /**
     * @return array<string, string>
     */
    private function players(Team $team): array
    {
        return TeamMember::sorted($team->members()->onTeamAt(now())->where('guest', false)->with('person')->get())
            ->mapWithKeys(fn (TeamMember $member) => [$member->id => $member->person->name])
            ->all();
    }

    private function authorizeCoach(Request $request, Team $team): void
    {
        abort_unless($team->isCoachedBy($request->user()), 403);
        abort_unless($team->tasks_enabled, 404);
    }

    private function authorizeTask(Request $request, Team $team, Task $task): void
    {
        $this->authorizeCoach($request, $team);
        abort_unless($task->team_id === $team->id, 404);
    }


}

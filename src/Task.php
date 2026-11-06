<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class Task extends Model
{
    use HasUuids;

    protected $table = 'sm_tasks';

    protected $attributes = [
        'weight' => 1,
        'undo_window' => 'any_time',
    ];

    protected $fillable = [
        'team_id',
        'title',
        'description',
        'schedule',
        'recurrence',
        'time',
        'starts_on',
        'ends_on',
        'within_periods',
        'lead_minutes',
        'min_people',
        'max_people',
        'effort_minutes',
        'effort_cents',
        'weight',
        'enrolment',
        'undo_window',
        'staff_only',
        'confirm_each_occurrence',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'schedule' => TaskSchedule::class,
            'enrolment' => TaskEnrolment::class,
            'undo_window' => TaskUndoWindow::class,
            'confirm_each_occurrence' => 'boolean',
            'staff_only' => 'boolean',
            'recurrence' => 'array',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'within_periods' => 'boolean',
            'lead_minutes' => 'integer',
            'min_people' => 'integer',
            'max_people' => 'integer',
            'effort_minutes' => 'integer',
            'effort_cents' => 'integer',
            'weight' => 'integer',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }

    public function dates(): HasMany
    {
        return $this->hasMany(TaskDate::class)->orderBy('date');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(TaskOccurrence::class)->orderBy('date')->orderBy('time');
    }

    public function upcomingOccurrences(): HasMany
    {
        return $this->occurrences()->whereNull('cancelled_at')->whereDate('date', '>=', now()->toDateString());
    }

    public function enlistments(): HasMany
    {
        return $this->hasMany(TaskEnlistment::class);
    }

    public function wholeTaskEnlistments(): HasMany
    {
        return $this->enlistments()->whereNull('occurrence_id')->with('skips')->orderBy('created_at');
    }

    public function events(): HasMany
    {
        return $this->hasMany(TaskEnlistmentEvent::class)->latest();
    }

    public function durationSummary(): ?string
    {
        return $this->effort_minutes === null ? null : __('kopling-sports-management::messages.tasks.duration', [
            'duration' => CarbonInterval::minutes($this->effort_minutes)->cascade()->forHumans(),
        ]);
    }

    public function peopleSummary(): ?string
    {
        $key = 'kopling-sports-management::messages.tasks.';

        return match (true) {
            $this->min_people !== null && $this->min_people === $this->max_people => trans_choice($key.'people_exact', $this->min_people),
            $this->min_people !== null && $this->max_people !== null => __($key.'people_range', ['min' => $this->min_people, 'max' => $this->max_people]),
            $this->min_people !== null => __($key.'people_min', ['min' => $this->min_people]),
            $this->max_people !== null => __($key.'people_max', ['max' => $this->max_people]),
            default => null,
        };
    }

    public function recurrenceRule(): ?TaskRecurrence
    {
        return $this->schedule === TaskSchedule::Recurring && $this->recurrence !== null
            ? TaskRecurrence::fromArray($this->recurrence)
            : null;
    }

    public function scheduleSummary(): string
    {
        $key = 'kopling-sports-management::messages.tasks.summary.';

        if ($this->schedule->isMatchBound()) {
            $summary = __($key.$this->schedule->value);

            return $this->lead_minutes ? __($key.'lead', ['schedule' => $summary, 'minutes' => $this->lead_minutes]) : $summary;
        }

        if ($this->schedule === TaskSchedule::Dates) {
            return trans_choice($key.'dates', $this->dates->count());
        }

        if ($this->schedule === TaskSchedule::Season) {
            $summary = __($key.'season', ['from' => $this->starts_on?->translatedFormat('j M Y'), 'until' => $this->ends_on?->translatedFormat('j M Y')]);

            return $this->within_periods ? __($key.'within_periods', ['schedule' => $summary]) : $summary;
        }

        $rule = $this->recurrenceRule();
        $weekday = fn (?int $day) => $day === null ? '' : now()->startOfWeek()->addDays($day - 1)->translatedFormat('l');

        $summary = match ($rule?->frequency) {
            TaskFrequency::Weekly => trans_choice($key.'weekly', $rule->interval, ['weekday' => $weekday($rule->weekday)]),
            TaskFrequency::Monthly => $rule->nth === null
                ? trans_choice($key.'monthly_day', $rule->interval, ['day' => $rule->day])
                : trans_choice($key.'monthly_nth', $rule->interval, ['nth' => __($key.'nth.'.$rule->nth), 'weekday' => $weekday($rule->weekday)]),
            TaskFrequency::Yearly => __($key.'yearly', ['date' => now()->setDate(2000, $rule->month ?? 1, $rule->day ?? 1)->translatedFormat('j F')]),
            null => '',
        };

        return $this->within_periods && $summary !== '' ? __($key.'within_periods', ['schedule' => $summary]) : $summary;
    }
}

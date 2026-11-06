<?php

declare(strict_types=1);

namespace Kopling\SportsManagement\Controllers;

/**
 * Validation `:attribute` names, taken from the labels the forms already show.
 */
class FieldNames
{
    private const LABELS = [
        'name' => 'name',
        'jersey_number' => 'jersey_number',
        'positions' => 'positions_label',
        'guest' => 'guest',
        'left_on' => 'left_on',
        'title' => 'tasks.title',
        'description' => 'tasks.description',
        'schedule' => 'tasks.schedule_label',
        'enrolment' => 'tasks.enrolment_label',
        'undo_window' => 'tasks.undo_window_label',
        'confirm_each_occurrence' => 'tasks.confirm_each',
        'lead_minutes' => 'tasks.lead_minutes',
        'dates' => 'tasks.dates_label',
        'dates.*.date' => 'tasks.date',
        'dates.*.time' => 'tasks.time',
        'frequency' => 'tasks.frequency_label',
        'interval' => 'tasks.interval_label',
        'weekday' => 'tasks.weekday',
        'monthly_by' => 'tasks.monthly_by_label',
        'day' => 'tasks.day',
        'nth' => 'tasks.nth',
        'month' => 'tasks.month',
        'time' => 'tasks.time',
        'starts_on' => 'tasks.starts_on',
        'ends_on' => 'tasks.ends_on',
        'within_periods' => 'periods.within',
        'min_people' => 'tasks.min_people',
        'max_people' => 'tasks.max_people',
        'effort_minutes' => 'tasks.effort_minutes',
        'effort_amount' => 'tasks.effort_amount',
        'weight' => 'tasks.weight',
        'team_member_id' => 'tasks.assign_player',
        'note' => 'tasks.link.note_label',
    ];

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return array_map(
            fn (string $key) => mb_strtolower(__('kopling-sports-management::messages.'.$key)),
            self::LABELS,
        );
    }
}

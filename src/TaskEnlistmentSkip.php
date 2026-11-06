<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Kopling\Core\Database\Model;

/**
 * One date a season-long sign-up does not cover: the regular person is off that day.
 */
class TaskEnlistmentSkip extends Model
{
    use HasUuids;

    protected $table = 'sm_task_enlistment_skips';

    protected $fillable = [
        'enlistment_id',
        'occurrence_id',
        'created_by',
    ];
}

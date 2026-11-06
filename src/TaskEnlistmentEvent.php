<?php

declare(strict_types=1);

namespace Kopling\SportsManagement;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kopling\Core\Database\Model;
use Kopling\Core\People\Person;

class TaskEnlistmentEvent extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'sm_task_enlistment_events';

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $fillable = [
        'task_id',
        'occurrence_id',
        'team_member_id',
        'action',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'action' => TaskEnlistmentAction::class,
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(TeamMember::class, 'team_member_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'created_by');
    }
}
